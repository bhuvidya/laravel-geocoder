<?php

namespace BhuVidya\Geocoder;

use Illuminate\Support\ServiceProvider;


class GeocoderServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;


    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // config file
        if ($this->app->runningInConsole()) {
            $source = realpath(__DIR__ . '/../config/geocoder.php');
            $this->publishes([ $source => config_path('geocoder.php') ], 'config');
        }
    }

    /**
     * Register everything.
     *
     * @return void
     */
    public function register()
    {
        $source = realpath(__DIR__ . '/../config/geocoder.php');
        $this->mergeConfigFrom($source, 'geocoder');
    }
}
