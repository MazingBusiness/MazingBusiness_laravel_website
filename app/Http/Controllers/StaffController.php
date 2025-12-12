<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppWebService;

class StaffController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_all_staffs'])->only('index');
    $this->middleware(['permission:add_staff'])->only('create');
    $this->middleware(['permission:edit_staff'])->only('edit');
    $this->middleware(['permission:delete_staff'])->only('destroy');
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index() {
    $staffs = Staff::paginate(10);
    return view('backend.staff.staffs.index', compact('staffs'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    $roles = Role::where('id', '!=', 1)->orderBy('id', 'desc')->get();
    return view('backend.staff.staffs.create', compact('roles'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function _backupstore(Request $request) {
    if (User::where('email', $request->email)->first() == null) {
      $user               = new User;
      $user->name         = $request->name;
      $user->email        = $request->email;
      $user->phone        = $request->mobile;
      $user->user_type    = "staff";
      $user->user_title   = $request->user_title;   // <-- save title
      $user->warehouse_id = $request->warehouse_id;
      $user->password     = Hash::make($request->password);
      if ($user->save()) {
        $staff          = new Staff;
        $staff->user_id = $user->id;
        $staff->role_id = $request->role_id;
        $user->assignRole(Role::findOrFail($request->role_id)->name);
        if ($staff->save()) {
          flash(translate('Staff has been inserted successfully'))->success();
          return redirect()->route('staffs.index');
        }
      }
    }

    flash(translate('Email already used'))->error();
    return back();
  }

  public function store(Request $request)
  {
      // 1) Validate (no DB unique rules)
      $request->validate([
          'name'         => 'required|string|max:255',
          'email'        => 'required|email',
          'mobile'       => 'required|string|max:20',
          'password'     => 'required|string|min:6',
          'role_id'      => 'required|exists:roles,id',
          'warehouse_id' => 'required|exists:warehouses,id',
          'user_title'   => 'required|in:dispatch,head_manager,super_boss,manager,manager_41',
      ]);

      // 2) Normalize phone to +91XXXXXXXXXX
      $digitsOnly = preg_replace('/\D+/', '', (string) $request->mobile);
      if (strlen($digitsOnly) > 10) $digitsOnly = substr($digitsOnly, -10);
      if (strlen($digitsOnly) !== 10) {
          return back()->withErrors(['mobile' => 'Please enter a valid 10 digit mobile number.'])->withInput();
      }
      $fullMobile = '+91' . $digitsOnly;

      // 3) Duplicate checks (code-level)
      if (User::where('email', $request->email)->exists()) {
          return back()->withErrors(['email' => 'This email is already taken.'])->withInput();
      }
      if (User::where('phone', $fullMobile)->exists()) {
          return back()->withErrors(['mobile' => 'This phone number is already taken.'])->withInput();
      }

      // 4) Create user
      $user               = new User();
      $user->name         = $request->name;
      $user->email        = $request->email;
      $user->phone        = $fullMobile;
      $user->user_type    = 'staff';
      $user->warehouse_id = $request->warehouse_id;
      $user->user_title   = $request->user_title;
      $user->password     = \Hash::make($request->password);
      $user->save();

      // 5) Create staff
      $staff          = new Staff();
      $staff->user_id = $user->id;
      $staff->role_id = $request->role_id;
      $staff->save();

      // 6) Assign Spatie role
      $roleName = Role::findOrFail($request->role_id)->name;
      $user->assignRole($roleName);

      // 7) Send WhatsApp alert (REMOVE debug dump)

      try {
          $this->sendStaffWelcomeWhatsApp($user->name, $user->phone, $user->email, $roleName);
      } catch (\Throwable $e) {
          \Log::error('WhatsApp send failed: '.$e->getMessage());
          // don’t block creation if WA fails
      }

      flash(translate('Staff has been inserted successfully'))->success();
      return redirect()->route('staffs.index');
  }



  protected function sendStaffWelcomeWhatsApp(string $name, string $phoneWithPlus, string $email, string $roleName)
  {
      // Convert +9170... -> 9170... if your API expects no '+'
      $recipient = ltrim($phoneWithPlus, '+');

      $payload = [
          'name' => 'staff_welcome_alert', // template name in WhatsApp Manager
          'language' => 'en_US',
          'components' => [[
              'type' => 'body',
              'parameters' => [
                  ['type' => 'text', 'text' => $name],     // {{1}}
                  ['type' => 'text', 'text' => $roleName], // {{2}}
                  ['type' => 'text', 'text' => $phoneWithPlus],    // {{3}}
              ]
          ]]
      ];
     
      $whatsapp = new WhatsAppWebService(); // adjust namespace if different
      $response= $whatsapp->sendTemplateMessage($recipient,$payload);
      return $response;
  }




  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id) {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id) {
    $staff = Staff::with('user.warehouse')->findOrFail(decrypt($id));
    $roles = $roles = Role::where('id', '!=', 1)->orderBy('id', 'desc')->get();
    return view('backend.staff.staffs.edit', compact('staff', 'roles'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function backup_update(Request $request, $id) {
    $staff              = Staff::findOrFail($id);
    $user               = $staff->user;
    $user->name         = $request->name;
    $user->email        = $request->email;
    $user->phone        = $request->mobile;
    $user->warehouse_id = $request->warehouse_id;
    if (strlen($request->password) > 0) {
      $user->password = Hash::make($request->password);
    }
    if ($user->save()) {
      $staff->role_id = $request->role_id;
      if ($staff->save()) {
        $user->syncRoles(Role::findOrFail($request->role_id)->name);
        flash(translate('Staff has been updated successfully'))->success();
        return redirect()->route('staffs.index');
      }
    }

    flash(translate('Something went wrong'))->error();
    return back();
  }

  public function update(Request $request, $id)
  {
      $staff = Staff::with('user')->findOrFail($id);
      $user  = $staff->user;

      // 1) Validate (ignore current user for unique checks if you add DB uniques later)
      $request->validate([
          'name'         => 'required|string|max:255',
          'email'        => 'required|email',
          'mobile'       => 'required|string|max:20', // user types 10 digits
          'password'     => 'nullable|string|min:6',
          'role_id'      => 'required|exists:roles,id',
          'warehouse_id' => 'required|exists:warehouses,id',
          'user_title'   => 'required|in:dispatch,head_manager,super_boss,manager',
      ]);

      // 2) Normalize phone to +91XXXXXXXXXX
      $digitsOnly = preg_replace('/\D+/', '', (string) $request->mobile);
      if (strlen($digitsOnly) > 10) {
          $digitsOnly = substr($digitsOnly, -10);
      }
      if (strlen($digitsOnly) !== 10) {
          return back()->withErrors(['mobile' => 'Please enter a valid 10 digit mobile number.'])->withInput();
      }
      $fullMobile = '+91' . $digitsOnly;

      // 3) Manual duplicate checks (code-level; skip current user)
      if (User::where('email', $request->email)->where('id', '!=', $user->id)->exists()) {
          return back()->withErrors(['email' => 'This email is already taken.'])->withInput();
      }
      if (User::where('phone', $fullMobile)->where('id', '!=', $user->id)->exists()) {
          return back()->withErrors(['mobile' => 'This phone number is already taken.'])->withInput();
      }

      // 4) Update user
      $user->name         = $request->name;
      $user->email        = $request->email;
      $user->phone        = $fullMobile;           // always +91
      $user->warehouse_id = $request->warehouse_id;
      $user->user_title   = $request->user_title;

      if ($request->filled('password')) {
          $user->password = Hash::make($request->password);
      }
      $user->save();

      // 5) Update staff + spatie role
      $staff->role_id = $request->role_id;
      $staff->save();

      $roleName = \App\Models\Role::findOrFail($request->role_id)->name;
      $user->syncRoles([$roleName]);

      flash(translate('Staff has been updated successfully'))->success();
      return redirect()->route('staffs.index');
  }


  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    User::destroy(Staff::findOrFail($id)->user->id);
    if (Staff::destroy($id)) {
      flash(translate('Staff has been deleted successfully'))->success();
      return redirect()->route('staffs.index');
    }

    flash(translate('Something went wrong'))->error();
    return back();
  }
}
