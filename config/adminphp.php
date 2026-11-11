<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin path and middleware
    |--------------------------------------------------------------------------
    |
    | URI prefix of the admin and the middleware applied to all its routes.
    | Admin users must log in; AdminPhp::auth() can restrict access further.
    |
    */

    'path' => env('ADMINPHP_PATH', 'admin'),

    'domain' => env('ADMINPHP_DOMAIN'),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authentication guard
    |--------------------------------------------------------------------------
    |
    | Admin users live in their own table and log in through this session
    | guard, apart from the users of the site. It is registered by AdminPhp
    | unless the application already defines a guard with this name.
    |
    */

    'guard' => 'adminphp',

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Locales available for translatable content. The first one is the
    | default locale: required translatable fields must be filled in it.
    |
    */

    'locales' => ['fr', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    |
    | Prefix applied to every table owned by AdminPhp, to avoid collisions
    | with the tables of the host application.
    |
    */

    'table_prefix' => 'adminphp_',

];
