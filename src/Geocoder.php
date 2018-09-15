<?php

namespace Bhuvidya\Geocoder;

use GuzzleHttp\Client as GuzzleClient;
use Carbon\Carbon;
use Cache;
use stdClass;
use Log;

class Geocoder
{
    public static $lastResponse;
    public static $lastStatus;

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

        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-remote-ip-%s', $ip);
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        if ($cache) {
            if ($value = Cache::get($cache_key)) {
                return $value;
            }
        }

        static::$lastResponse = $response = static::getHttpClient()->get(
            'http://freegeoip.net/json/' . $ip,
            [
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        if (!$response) {
            return false;
        }

        $ret = @json_decode($response->getBody());

        if ($cache) {
            Cache::put($cache_key, $ret, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
        }

        return $ret;
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


    /**
     * geocode an address using the Google Maps API
     *
     * @param string $addr - the address to geocode
     * @param bool $orig - if true then stash the original returned data
     * @return object | false
     */
    public static function geocode($addr, $orig = true)
    {
        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-addr-%s', $addr);

        if ($cache) {
            if ($value = Cache::get($cache_key)) {
                if (!$orig) {
                    unset($value->_orig);
                }
                return $value;
            }
        }

        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false&key=%s',
            urlencode($addr),
            config('geocoder.google_maps_api_key')
        );

        $headers = [ 'headers' => [ 'Accept' => 'application/json' ] ];

        while (true) {
            static::$lastResponse = $response = static::getHttpClient()->get($url, $headers);

            if (!$response) {
                return false;
            }

            if (!$json = @json_decode(iconv('ISO-8859-1', 'UTF-8', $response->getBody()))) {
                return false;
            }

            $status = static::$lastStatus = $json->status;

            if ($status == 'OK') {
                $info = new stdClass();

                // Successful geocode
                // Format: Longitude, Latitude, Altitude
                $best = $json->results[0];
                $info->address = $best->formatted_address;

                $geo = $best->geometry;
                $info->center = (object) array(
                    'lat' => $geo->location->lat,
                    'lng' => $geo->location->lng
                );

                if (isset($geo->bounds)) {
                    $info->box = (object) array(
                        'north' => $geo->bounds->northeast->lat,
                        'south' => $geo->bounds->southwest->lat,
                        'east' => $geo->bounds->northeast->lng,
                        'west' => $geo->bounds->southwest->lng
                    );
                }

                $bits = $best->address_components;
                foreach ($bits as $bit) {
                    if ($bit->types && $bit->types[0] == 'country') {
                        $info->country = $bit->long_name;
                        $info->countryCode = $bit->short_name;
                    }
                    if ($bit->types && $bit->types[0] == 'administrative_area_level_1') {
                        $info->province = $bit->short_name;
                    }
                    if ($bit->types && $bit->types[0] == 'postal_code') {
                        $info->postal_code = $bit->long_name;
                    }
                }

                $info->_orig = $json;

                if ($cache) {
                    Cache::put($cache_key, $info, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
                }

                if (!$orig) {
                    unset($info->_orig);
                }

                return $info;

            } elseif ($status == 'OVER_QUERY_LIMIT') {
                // delay then try again
                usleep(config('geocoder.rate_limit_delay_secs') * 1000000);
            } elseif ($status == 'UNKNOWN_ERROR') {
                return false;
            } else {
                return false;
            }
        }
    }

    /**
     * reverse-geocode an address
     *
     * @param float $lat
     * @param float $lng
     * @param bool $orig - if true then original response is stahsed in return value
     * @return object | false
     */
    public static function reverseGeocode($lat, $lng, $orig = true)
    {
        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-latlng-%f-%f', $lat, $lng);

        if ($cache) {
            if ($value = Cache::get($cache_key)) {
                if (!$orig) {
                    unset($value->_orig);
                }
                return $value;
            }
        }

        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?latlng=%f,%f&sensor=false&key=%s',
            $lat,
            $lng,
            config('geocoder.google_maps_api_key')
        );

        $headers = [ 'headers' => [ 'Accept' => 'application/json' ] ];

        while (true) {
            static::$lastResponse = $response = static::getHttpClient()->get($url, $headers);

            if (!$response) {
                return false;
            }

            if (!$json = json_decode(iconv('ISO-8859-1', 'UTF-8', $response->getBody()))) {
                return false;
            }

            $status = static::$lastStatus = $json->status;

            if ($json->status == 'OK') {
                $info = new stdClass();

                $info->lat = $lat;
                $info->lng = $lng;
                $info->addresses = array();

                if ($json->results) {
                    foreach ($json->results as $result) {
                        $info->addresses[] = $result->formatted_address;
                    }
                }

                $info->_orig = $json;

                if ($cache) {
                    Cache::put($cache_key, $info, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
                }

                if (!$orig) {
                    unset($info->_orig);
                }

                return $info;

            } elseif ($status == 620) {
                // delay then try again
                usleep(config('geocoder.rate_limit_delay_secs') * 1000000);
            } else {
                // failure to geocode
                return false;
            }
        }
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
