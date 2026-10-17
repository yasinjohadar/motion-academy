<?php

return [

    'logical_disk' => 'media',

    'directory' => 'library',

    'max_upload_kb' => 10240,

    /* Lesson video uploads. PHP's upload_max_filesize/post_max_size must allow this too. */
    'max_video_upload_kb' => (int) env('MEDIA_MAX_VIDEO_UPLOAD_KB', 512000),

    'max_attachment_upload_kb' => (int) env('MEDIA_MAX_ATTACHMENT_UPLOAD_KB', 51200),

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
