<?php

/* vim: set ai expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Bhuvidya\Geocoder;

use GuzzleHttp\Client as GuzzleClient;
use stdClass;
use Log;

class Geocoder
{
    public static $lastResponse;

    protected static $httpClient;


    /**
     * geocode ip address using http://freegeoip.net
     *
     * @param string $ip - null => use REMOTE_ADDR
     * @return object | false
     */
    public static function geocodeRemoteIP($ip = null)
    {
        /*******
        // this geoip service returns data like so
        {
            ip: "27.32.138.126",
            country_code: "AU",
            country_name: "Australia",
            region_code: "VIC",
            region_name: "Victoria",
            city: "North Fitzroy",
            zip_code: "3068",
            time_zone: "Australia/Melbourne",
            latitude: -37.7833,
            longitude: 144.9667,
            metro_code: 0
        }
        ********/

        static::$lastResponse = $response = $this->getHttpClient()->get(
            'http://freegeoip.net/json/' . ($ip ?: $_SERVER['REMOTE_ADDR']),
            [
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        if (!$response) {
            return false;
        }

        return @json_decode($response->getBody());
    }

    /**
     * get the country code or name related to the given IP address
     *
     * @param string $ip - null => use REMOTE_ADDR
     * @param bool $code - true => return country code, o/w name
     * @return string | false
     */
    public static function remoteIPCountry($ip = null, $code = true)
    {
        if (!$info = static::geocodeRemoteIP($ip)) {
            return false;
        }

        return $code ? $info->country_code : $info->country_name;
    }

    /**
     * get the lat/lng of the remote IP address
     *
     * @param string $ip - null => use REMOTE_ADDR
     * @return array | false
     */
    public static function remoteIPLatLng($ip = null)
    {
        if (!$info = static::geocodeRemoteIP($ip)) {
            return false;
        }

        return [ 'lat' => $info->latitude, 'lng' => $info->longitude ];
    }


    /*
    |--------------------------------------------------------------------------
    | helpers
    |--------------------------------------------------------------------------
    */

    /**
     * create and return the guzzle http client
     *
     * @return GuzzleHttp\Client
     */
    protected static function getHttpClient()
    {
        if (is_null(static::$httpClient)) {
            static::$httpClient = new GuzzleClient();
        }

        return static::$httpClient;
    }
}
