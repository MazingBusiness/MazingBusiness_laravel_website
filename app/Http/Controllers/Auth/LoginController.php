<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use App\Models\Staff;
use App\Utility\WhatsAppUtility;
use Auth;
use CoreComponentRepository;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Session;
use Socialite;

class LoginController extends Controller {
  /*
  |--------------------------------------------------------------------------
  | Login Controller
  |--------------------------------------------------------------------------
  |
  | This controller handles authenticating users for the application and
  | redirecting them to your home screen. The controller uses a trait
  | to conveniently provide its functionality to your applications.
  |
   */

  use AuthenticatesUsers;

  protected function attemptLogin(Request $request)
    {
        $credentials = $this->credentials($request);
    
        $user = $this->guard()->getProvider()->retrieveByCredentials($credentials);
    
        if ($user) {
            // ❌ Check if user is banned
            if ($user->banned == 1) {
                return false;
            }
    
            // ❌ Check if user is not approved
            if ($user->unapproved == 1) {
                return false;
            }
    
            // ✅ Proceed if credentials, login_otp, or verification_code match
            if (
                $this->guard()->getProvider()->validateCredentials($user, $credentials) ||
                $user->login_otp === $credentials['password'] ||
                $user->verification_code === $credentials['password']
            ) {
                $this->guard()->login($user, $request->filled('remember'));
                return true;
            }
        }
    
        return false;
    }

  /**
   * Where to redirect users after login.
   *
   * @var string
   */
  /*protected $redirectTo = '/';*/

  /**
   * Redirect the user to the Google authentication page.
   *
   * @return \Illuminate\Http\Response
   */
  public function redirectToProvider($provider) {
    if (request()->get('query') == 'mobile_app') {
      request()->session()->put('login_from', 'mobile_app');
    }
    if ($provider == 'apple') {
      return Socialite::driver("sign-in-with-apple")
        ->scopes(["name", "email"])
        ->redirect();
    }
    return Socialite::driver($provider)->redirect();
  }

  public function handleAppleCallback(Request $request) {
    try {
      $user = Socialite::driver("sign-in-with-apple")->user();
    } catch (\Exception $e) {
      flash("Something Went wrong. Please try again.")->error();
      return redirect()->route('user.login');
    }
    //check if provider_id exist
    $existingUserByProviderId = User::where('provider_id', $user->id)->first();

    if ($existingUserByProviderId) {
      $existingUserByProviderId->access_token  = $user->token;
      $existingUserByProviderId->refresh_token = $user->refreshToken;
      if (!isset($user->user['is_private_email'])) {
        $existingUserByProviderId->email = $user->email;
      }
      $existingUserByProviderId->save();
      //proceed to login
      auth()->login($existingUserByProviderId, true);
    } else {
      //check if email exist
      $existing_or_new_user = User::firstOrNew([
        'email' => $user->email,
      ]);
      $existing_or_new_user->provider_id   = $user->id;
      $existing_or_new_user->access_token  = $user->token;
      $existing_or_new_user->refresh_token = $user->refreshToken;
      $existing_or_new_user->provider      = 'apple';
      if (!$existing_or_new_user->exists) {
        $existing_or_new_user->name = 'Apple User';
        if ($user->name) {
          $existing_or_new_user->name = $user->name;
        }
        $existing_or_new_user->email             = $user->email;
        $existing_or_new_user->email_verified_at = date('Y-m-d H:m:s');
      }
      $existing_or_new_user->save();

      auth()->login($existing_or_new_user, true);
    }

    if (session('temp_user_id') != null) {
      Cart::where('temp_user_id', session('temp_user_id'))
        ->update([
          'user_id'      => auth()->user()->id,
          'temp_user_id' => null,
        ]);

      Session::forget('temp_user_id');
    }

    if (session('link') != null) {
      return redirect(session('link'));
    } else {
      if (auth()->user()->user_type == 'seller') {
        return redirect()->route('seller.dashboard');
      }
      return redirect()->route('dashboard');
    }
  }
  /**
   * Obtain the user information from Google.
   *
   * @return \Illuminate\Http\Response
   */
  public function handleProviderCallback(Request $request, $provider) {
    if (session('login_from') == 'mobile_app') {
      return $this->mobileHandleProviderCallback($request, $provider);
    }
    try {
      if ($provider == 'twitter') {
        $user = Socialite::driver('twitter')->user();
      } else {
        $user = Socialite::driver($provider)->stateless()->user();
      }
    } catch (\Exception $e) {
      flash("Something Went wrong. Please try again.")->error();
      return redirect()->route('user.login');
    }

    //check if provider_id exist
    $existingUserByProviderId = User::where('provider_id', $user->id)->first();

    if ($existingUserByProviderId) {
      $existingUserByProviderId->access_token = $user->token;
      $existingUserByProviderId->save();
      //proceed to login
      auth()->login($existingUserByProviderId, true);
    } else {
      //check if email exist
      $existingUser = User::where('email', '!=', null)->where('email', $user->email)->first();

      if ($existingUser) {
        //update provider_id
        $existing_User               = $existingUser;
        $existing_User->provider_id  = $user->id;
        $existing_User->provider     = $provider;
        $existing_User->access_token = $user->token;
        $existing_User->save();

        //proceed to login
        auth()->login($existing_User, true);
      } else {
        //create a new user
        $newUser                    = new User;
        $newUser->name              = $user->name;
        $newUser->email             = $user->email;
        $newUser->email_verified_at = date('Y-m-d Hms');
        $newUser->provider_id       = $user->id;
        $newUser->provider          = $provider;
        $newUser->access_token      = $user->token;
        $newUser->save();
        //proceed to login
        auth()->login($newUser, true);
      }
    }

    if (session('temp_user_id') != null) {
      Cart::where('temp_user_id', session('temp_user_id'))
        ->update([
          'user_id'      => auth()->user()->id,
          'temp_user_id' => null,
        ]);

      Session::forget('temp_user_id');
    }

    if (session('link') != null) {
      return redirect(session('link'));
    } else {
      if (auth()->user()->user_type == 'seller') {
        return redirect()->route('seller.dashboard');
      }
      return redirect()->route('dashboard');
    }
  }

  public function mobileHandleProviderCallback($request, $provider) {
    $return_provider = '';
    $result          = false;
    if ($provider) {
      $return_provider = $provider;
      $result          = true;
    }
    return response()->json([
      'result'   => $result,
      'provider' => $return_provider,
    ]);
  }

  /**
   * Validate the user login request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return void
   *
   * @throws \Illuminate\Validation\ValidationException
   */
  protected function validateLogin(Request $request) {
    $request->validate([
      'email'    => 'required_without:phone',
      'phone'    => 'required_without:email',
      'password' => 'required|string',
    ]);
  }

  /**
   * Get the needed authorization credentials from the request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  protected function credentials(Request $request) {

    if ($request->get('phone') != null) {
      return ['phone' => "+{$request['country_code']}{$request['phone']}", 'password' => $request->get('password')];
    } elseif ($request->get('email') != null) {
      return $request->only($this->username(), 'password');
    }
  }

  /**
   * Check user's role and redirect user based on their role
   * @return
   */
  public function authenticated(Request $request, $user) {


    if (session('temp_user_id') != null) {
      Cart::where('temp_user_id', session('temp_user_id'))
        ->update(
          [
            'user_id'      => auth()->user()->id,
            'temp_user_id' => null,
          ]
        );

      Session::forget('temp_user_id');
    }
    $user = auth()->user();
    // echo "<pre>";print_r($user);die;
    if (auth()->user()->user_type == 'admin') {
      CoreComponentRepository::instantiateShopRepository();
      return redirect()->route('admin.dashboard');
    } elseif (auth()->user()->user_type == 'staff') {
        // Change redirect for staff user type
         // Edit by dipak start
            $userId = auth()->id();
            $specialStaffIds = [180, 169, 25606];
            $staff = \App\Models\Staff::where('user_id', $userId)->first();
            if (($staff && $staff->role_id == 4) || in_array($userId, $specialStaffIds)) {
                CoreComponentRepository::instantiateShopRepository();
                return redirect()->route('admin.dashboard');
            }
        // Edit by dipak end

        return redirect('/customers');
    } elseif (auth()->user()->user_type == 'seller') {
      return redirect()->route('seller.dashboard');
    } elseif (auth()->user()->user_type == 'customer') {
      return redirect('/quick-order');
    } elseif(auth()->user()->user_type == 'impx_customer'){
      $id = auth()->user()->id;
      if (auth()->user()) {
        Cart::where('user_id', auth()->user()->id)->delete();
      }  
      $this->guard()->logout();  
      $request->session()->invalidate();
      return redirect('https://impex.mazingbusiness.com/users/impex-login/'. base64_encode($id + 11121984));
    }else {

        if (session('link') != null) {
          return redirect(session('link'));
        } else {
          return redirect()->route('dashboard');
        }
      }
    }

  /**
   * Get the failed login response instance.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Symfony\Component\HttpFoundation\Response
   *
   * @throws \Illuminate\Validation\ValidationException
   */
  protected function sendFailedLoginResponse(Request $request) {
    // WhatsAppUtility::contactSupport($request);
    flash(translate('Invalid login credentials'))->error();
    return back();
  }

  /**
   * Log the user out of the application.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function logout(Request $request) {
    if (auth()->user() != null && (auth()->user()->user_type == 'admin')) {
      $redirect_route = 'login';
    } else {
      $redirect_route = 'home';
    }

    //User's Cart Delete
    if (auth()->user()) {
      Cart::where('user_id', auth()->user()->id)->delete();
    }

    $this->guard()->logout();

    $request->session()->invalidate();

    if ($request->has('modal_logout')) {
      flash('Verification failed, please try again')->warning();
    }

    return $this->loggedOut($request) ?: redirect()->route($redirect_route);
  }

  public function account_deletion(Request $request) {
    $redirect_route = 'home';

    if (auth()->user()) {
      Cart::where('user_id', auth()->user()->id)->delete();
    }

    // if (auth()->user()->provider) {
    //     $social_revoke =  new SocialRevoke;
    //     $revoke_output = $social_revoke->apply(auth()->user()->provider);

    //     if ($revoke_output) {
    //     }
    // }

    $auth_user = auth()->user();
    $auth_user->customer_products()->delete();

    User::destroy(auth()->user()->id);

    auth()->guard()->logout();
    $request->session()->invalidate();

    flash("Your account deletion successfully done.")->success();
    return redirect()->route($redirect_route);
  }

  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct() {
    $this->middleware('guest')->except(['logout', 'account_deletion']);
  }
}
