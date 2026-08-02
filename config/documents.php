<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documents master key (envelope encryption)
    |--------------------------------------------------------------------------
    |
    | Wraps per-organization data keys. Prefer DOCUMENTS_MASTER_KEY in production.
    | Falls back to a derived value from APP_KEY so local/tests work without a
    | separate secret. Never log or return this value.
    |
    */
    'master_key' => env('DOCUMENTS_MASTER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Private local disk (not the public profile_photo path). Paths are scoped
    | under documents/{placement_organization_id}/… so isolated dioceses can
    | export their own tree once Slice 12 lands.
    |
    */
    'disk' => env('DOCUMENTS_DISK', 'local'),

    'path_prefix' => 'documents',

];
