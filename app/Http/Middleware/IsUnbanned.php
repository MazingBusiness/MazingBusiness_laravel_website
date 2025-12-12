<?php

namespace App\Http\Middleware;

use Auth;
use Closure;

class IsUnbanned {
  public function handle($request, Closure $next) {
    if (auth()->check() && auth()->user()->banned) {
      $redirect_to = "";
      if (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff') {
        $redirect_to = "login";
      } else {
        $redirect_to = "home";
      }
      auth()->logout();
      $message = translate("Your Account is Under Verification.");
      flash($message);
      return redirect()->route($redirect_to);
    }
    return $next($request);
  }
}
