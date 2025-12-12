<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider {
  /**
   * The policy mappings for the application.
   *
   * @var array
   */
  protected $policies = [
    //'App\Model' => 'App\Policies\ModelPolicy',
  ];

  /**
   * Register any authentication / authorization services.
   *
   * @return void
   */

  //old code - changes done on 18 aug 2025
  // public function boot() {
  //   $this->registerPolicies();

  //   // Implicitly grant "Super Admin" role all permissions
  //   Gate::before(function ($user, $ability) {
  //     return $user->hasRole('Super Admin') ? true : null;



  //   });


  // }

  public function boot()
{
    $this->registerPolicies();

    Gate::before(function ($user, $ability) {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $title = strtolower(trim((string) $user->user_title));
        $is41Manager = in_array($title, ['manager_41','41 manager','41_manager','41manager'], true);

        if ($is41Manager) {
            $allow = [
                // product
                'add_new_product',
                'show_all_products',
                'show_in_house_products',
                'show_seller_products',
                'product_edit',
                'product_duplicate',
                'product_delete',

                // orders
                'view_all_orders',
                'view_inhouse_orders',
                'view_seller_orders',
                'view_pickup_point_orders',
                'view_order_details',   // ✅ add this
            ];

            if (in_array($ability, $allow, true)) {
                return true;
            }
        }

        return null;
    });
}


}
