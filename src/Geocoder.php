<?php

namespace BhuVidya\Geocoder;

use GuzzleHttp\Client as GuzzleClient;
use Carbon\Carbon;
use Cache;
use stdClass;
use Log;


/**
 * Provide support for easy geocoding, mainly via Google's geocoding API.
 */
class Geocoder
{
    public static $lastResponse;
    public static $lastStatus;

    protected static $httpClient;


    /**
     * Geocode an address using the Google Maps API.
     *
     * @param string $addr - the address to geocode
     * @param array $opts
     *                  bool ['orig'] - also stash the original returned data object
     *                  bool ['refresh'] - don't use cached value
     *                  string ['region'] - for region biased results - see 
     *                                https://developers.google.com/maps/documentation/geocoding/intro#RegionCodes
     * @return object | false
     */
    public static function geocode($addr, array $opts = [])
    {
        $opts = array_replace([
            'orig' => true,
            'refresh' => false,
            'region' => null,
        ], $opts);

        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-addr-%s-%s', $addr, json_encode($opts));

        if (!$opts['refresh'] && $cache) {
            if ($value = Cache::get($cache_key)) {
                if (!$opts['orig']) {
                    unset($value->_orig);
                    unset($value->_orig_url);
                }
                return $value;
            }
        }

        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false&key=%s',
            urlencode($addr),
            config('geocoder.google_maps_api_key')
        );

        if ($opts['region']) {
            $url .= "&region={$opts['region']}";
        }

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


                // we always have the original value in our cached data
                $info->_orig = $json;
                $info->_orig_url = $url;

                if ($cache) {
                    Cache::put($cache_key, $info, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
                }

                if (!$opts['orig']) {
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
     * Reverse-geocode an address.
     *
     * @param float $lat
     * @param float $lng
     * @param array $opts
     *                  bool ['orig'] - also stash the original returned data object
     *                  bool ['refresh'] - don't use cached value
     * @return object | false
     */
    public static function reverseGeocode($lat, $lng, array $opts = [])
    {
        $opts = array_replace([
            'orig' => true,
            'refresh' => false,
        ], $opts);

        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-latlng-%f-%f-%s', $lat, $lng, json_encode($opts));

        if (!$opts['refresh'] && $cache) {
            if ($value = Cache::get($cache_key)) {
                if (!$opts['orig']) {
                    unset($value->_orig);
                    unset($value->_orig_url);
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

                // we always stash original return values
                $info->_orig = $json;
                $info->_orig_url = $url;

                if ($cache) {
                    Cache::put($cache_key, $info, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
                }

                if (!$opts['orig']) {
                    unset($info->_orig);
                    unset($info->_orig_url);
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

    /**
     * Geocode an IP address using https://ipstack.com
     *
     * @param string $ip - null => use REMOTE_ADDR
     * @param array $opts
     *                  bool ['orig'] - also stash the original returned data object
     *                  bool ['refresh'] - don't use cached value
     * @return object | false
     */
    public static function geocodeRemoteIP($ip = null, array $opts = [])
    {
        $opts = array_replace([
            'orig' => true,
            'refresh' => false,
        ], $opts);

        /*******************************************
        // this geoip service returns data like so
        {
            ip: "175.34.200.120",
            type: "ipv4",
            continent_code: "OC",
            continent_name: "Oceania",
            country_code: "AU",
            country_name: "Australia",
            region_code: "VIC",
            region_name: "Victoria",
            city: "Narre Warren North",
            zip: "3804",
            latitude: -37.98112869262695,
            longitude: 145.31527709960938,
            location: {
                geoname_id: null,
                capital: "Canberra",
                languages: [
                    {
                        code: "en",
                        name: "English",
                        native: "English"
                    }
                ],
                country_flag: "http://assets.ipstack.com/flags/au.svg",
                country_flag_emoji: "🇦🇺",
                country_flag_emoji_unicode: "U+1F1E6 U+1F1FA",
                calling_code: "61",
                is_eu: false
            }
        }
        ********************************************/

        $cache = config('geocoder.cache_results');
        $cache_key = sprintf('geocode-remote-ip-%s', $ip);
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        if (!$opts['refresh'] && $cache) {
            if ($value = Cache::get($cache_key)) {
                if (!$opts['orig']) {
                    unset($value['_orig_url']);
                }
                return $value;
            }
        }

        $url = sprintf(
            '%s://api.ipstack.com/%s?access_key=%s',
            config('ipstack_https') ? 'https' : 'http',
            $ip,
            config('geocoder.ipstack_api_key')
        );

        static::$lastResponse = $response = static::getHttpClient()->get(
            $url,
            [
                'headers' => [ 'Accept' => 'application/json' ],
            ]
        );

        if (!$response) {
            return false;
        }

        $ret = @json_decode($response->getBody(), true);

        if ($ret) {
            $ret['_orig_url'] = $url;
        }

        if ($cache) {
            Cache::put($cache_key, $ret, Carbon::now()->addMinutes(config('geocoder.cache_for_mins')));
        }

        if (!$opts['orig']) {
            unset($ret['_orig_url']);
        }

        return $ret;
    }

    /**
     * Get the country code or name related to the given IP address.
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

        return $code ? $info['country_code'] : $info['country_name'];
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

        return [ 'lat' => $info['latitude'], 'lng' => $info['longitude'] ];
    }


    /*
    |--------------------------------------------------------------------------
    | Local helpers.
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
