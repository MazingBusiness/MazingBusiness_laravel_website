<?php
    namespace App;

    use Illuminate\Foundation\Application as BaseApplication;

    class Application extends BaseApplication
    {
        /**
         * Backward-compat for old packages that call $this->app->share()
         *
         * This behaves like the old Container::share() helper:
         * it returns a closure that lazily creates a single shared instance.
         */
        public function share(\Closure $closure)
        {
            return function ($app) use ($closure) {
                static $object;

                if ($object === null) {
                    $object = $closure($app);
                }

                return $object;
            };
        }
    }