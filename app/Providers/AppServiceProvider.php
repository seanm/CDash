<?php

namespace App\Providers;

define('FMT_TIME', 'H:i:s');  // time
define('FMT_DATE', 'Y-m-d');  // date
define('FMT_DATETIMESTD', 'Y-m-d H:i:s');  // date and time standard
define('FMT_DATETIME', 'Y-m-d\TH:i:s');  // date and time
define('FMT_DATETIMETZ', 'Y-m-d\TH:i:s T');  // date and time with time zone
define('FMT_DATETIMEMS', 'Y-m-d\TH:i:s.u');  // date and time with milliseconds
define('FMT_DATETIMEDISPLAY', 'M d, Y - H:i T');  // date and time standard

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

require_once 'include/common.php';
require_once 'include/pdo.php';

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extendImplicit('complexity', 'App\Validators\Password@complexity');

        /** For migrations on MySQL older than 5.7.7 **/
        if (config('database.default') !== 'pgsql') {
            Schema::defaultStringLength(191);
        }

        // Serve content over https in production mode.
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        URL::forceRootUrl(Config::get('app.url'));

        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
