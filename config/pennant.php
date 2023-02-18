<?php

use Illuminate\Support\Facades\Auth;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pennant Store
    |--------------------------------------------------------------------------
    |
    | Here you will specify the default store that Pennant should use when
    | storing and resolving feature flag values. Pennant ships with the
    | ability to store flag values in an in-memory array or database.
    |
    | Supported: "array", "database"
    |
    */

    'default' => env('PENNANT_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure each of the stores that should be available to
    | Pennant. These stores shall be used to store resolved feature flag
    | values - you may configure as many as your application requires.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pennant Default Scopes
    |--------------------------------------------------------------------------
    |
    | Here you may specify a handler method for any given scope when using
    | class based features. You may also specify how to resolve default
    | scope for convinience. Resolvers are overridden by given scope.
    |
    */

    'subscribe' => [
        App\Models\User::class => 'flagUser',
    ],

    'resolve' => [
        App\Models\User::class => fn () => Auth::user(),
    ],

];
