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

    /*
    | Where "Voir le site" in the admin leads. Defaults to the application URL.
    */

    'site_url' => env('ADMINPHP_SITE_URL'),

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
    | Passwords
    |--------------------------------------------------------------------------
    |
    | How long, in minutes, a "forgotten password" link and an invitation
    | link stay valid. Both use the adminphp_password_reset_tokens table.
    |
    */

    'passwords' => [
        'reset_expire' => 60,
        'invitation_expire' => 60 * 24 * 7,
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Media library
    |--------------------------------------------------------------------------
    |
    | Where uploaded images are stored, and what is accepted. The disk must be
    | public for images to show on the site (run php artisan storage:link
    | with the "public" disk). max_size is in kilobytes. SVG is not accepted
    | on purpose: it can carry scripts.
    |
    */

    'media' => [
        'disk' => env('ADMINPHP_MEDIA_DISK', 'public'),
        'directory' => 'adminphp',
        'max_size' => 10240,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],

        /*
        | Smaller copies made of every uploaded image, by name => width in px, for
        | responsive images on the site ($media->url('md'), $media->srcset(),
        | <x-adminphp::image>). An image is never enlarged: sizes wider than the
        | original are skipped. Run php artisan adminphp:media:regenerate after
        | changing them. GIFs (maybe animated) keep only their original.
        */

        'sizes' => [
            'sm' => 480,
            'md' => 960,
            'lg' => 1600,
            'xl' => 2400,
        ],

        'variant_format' => 'webp',
        'variant_quality' => 80,

        // "gd" or "imagick".
        'driver' => env('ADMINPHP_IMAGE_DRIVER', 'gd'),

        // Queue connection used to make the copies (null: the default one).
        'queue' => env('ADMINPHP_MEDIA_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Edit locks
    |--------------------------------------------------------------------------
    |
    | While someone edits an item, others see it read-only and may take over.
    | The admin renews the lock every 30 seconds; a lock not renewed for
    | `ttl` seconds (a closed tab, a lost connection) is released.
    |
    */

    'locks' => [
        'ttl' => 120,
    ],

];
