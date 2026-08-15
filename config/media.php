<?php

return [

    'logical_disk' => 'media',

    'directory' => 'library',

    'max_upload_kb' => 10240,

    'keep_original' => true,

    'webp_quality' => 82,

    'max_width' => 2560,

    'driver' => env('MEDIA_IMAGE_DRIVER', extension_loaded('imagick') ? 'imagick' : 'gd'),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ],

    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],

    /*
    |--------------------------------------------------------------------------
    | Responsive sizes (scale down, no crop)
    |--------------------------------------------------------------------------
    */
    'sizes' => [
        'thumb' => 150,
        'medium' => 768,
        'large' => 1280,
    ],

    /*
    | Skip destructive conversion for these (store as-is + optional thumb for raster)
    */
    'passthrough_mimes' => [
        'image/svg+xml',
        'image/gif',
    ],

];
