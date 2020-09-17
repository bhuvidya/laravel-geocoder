# Laravel Geocoder

[![License](https://poser.pugx.org/bhuvidya/laravel-geocoder/license?format=flat-square)](https://packagist.org/packages/bhuvidya/laravel-geocoder)
[![Total Downloads](https://poser.pugx.org/bhuvidya/laravel-geocoder/downloads?format=flat-square)](https://packagist.org/packages/bhuvidya/laravel-geocoder)
[![Latest Stable Version](https://poser.pugx.org/bhuvidya/laravel-geocoder/v/stable?format=flat-square)](https://packagist.org/packages/bhuvidya/laravel-geocoder)
[![Latest Unstable Version](https://poser.pugx.org/bhuvidya/laravel-geocoder/v/unstable?format=flat-square)](https://packagist.org/packages/bhuvidya/laravel-geocoder)

**Note I have now switched the semver versioning for my Laravel packages to "match" the latest supported Laravel version.**


A Laravel 5 package to make geocoding a breeze.

**Please note that this package was tested on Laravel 5.5 - I cannot guarantee it will work on earlier versions. Sorry.**

## Installation

Add `bhuvidya/laravel-geocoder` to your app:

    $ composer require "bhuvidya/laravel-geocoder"
    

**If you're using Laravel 5.5, you don't have to edit `app/config/app.php`.**

Otherwise, edit `app/config/app.php` and add the service provider:

    'providers' => [
        'Bhuvidya\Geocoder\GeocoderServiceProvider',
    ]


## Configuration

You can elect to manage your own configuration. This is an optional step, but only if you define
your Google Maps API Key in your `.env` file via `GOOGLE_MAPS_API_KEY`.

Otherwise run the following command

    $ php artisan vendor:publish --provider='Bhuvidya\Geocoder\GeocoderServiceProvider' --tag=config

The config file can then be found at `config/geocoder.php`.


### Configuration Options

TODO


## Service Class

TODO
