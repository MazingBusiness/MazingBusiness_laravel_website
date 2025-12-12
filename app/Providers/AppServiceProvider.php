<?php

namespace App\Providers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot() {
    Schema::defaultStringLength(191);
    Paginator::useBootstrap();
    Collection::macro('locate', function (string $attribute, string $value) {
      return $this->filter(function ($item) use ($attribute, $value) {
        return strtolower($item[$attribute]) == strtolower($value);
      });
    });
    if (!Collection::hasMacro('paginate')) {
      Collection::macro(
        'paginate',
        function ($perPage = 15, $page = null, $options = []) {
          $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
          return (new LengthAwarePaginator(
            $this->forPage($page, $perPage),
            $this->count(),
            $perPage,
            $page,
            $options
          ))->withPath('');
        }
      );
    }
  }

  /**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {
      // 🔁 Backward-compat for old packages that call $this->app->share()
      if (! method_exists($this->app, 'share')) {
          $this->app->macro('share', function (Closure $closure) {
              return function ($app) use ($closure) {
                  static $object;

                  if ($object === null) {
                      $object = $closure($app);
                  }

                  return $object;
              };
          });
      }

      // ... your existing register() code (if any) ...
  }
}
