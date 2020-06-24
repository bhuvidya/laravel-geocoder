<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google API Keys - some geocoding is done via the Google Maps API
    |--------------------------------------------------------------------------
    */
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | IPStack Key - some geocoding is done via ipstack.com
    |--------------------------------------------------------------------------
    */
    'ipstack_api_key' => env('IPSTACK_API_KEY'),
    'ipstack_https' => env('IPSTACK_HTTPS'),

    /*
    |--------------------------------------------------------------------------
    | Results can be cached if you want
    |--------------------------------------------------------------------------
    */
    'cache_results' => true,
    'cache_for_mins' => 60*24*7,    // cache for 1 week by default

    /*
    |--------------------------------------------------------------------------
    | if we exceed rate limit, we need to sleep for a bit before retrying
    |--------------------------------------------------------------------------
    */
    'rate_limit_delay_secs' => 0.1,
];
