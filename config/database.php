<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        // 1. Admin DB
        'pgsql_admin' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_ADMIN', '127.0.0.1'),
            'port' => env('DB_PORT_ADMIN', '5432'), // psql चा डीफॉल्ट पोर्ट 5432 असतो
            'database' => env('DB_DATABASE_ADMIN', 'tizzy_admin_db'),
            'username' => env('DB_USERNAME_ADMIN', 'postgres'),
            'password' => env('DB_PASSWORD_ADMIN', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // 2. Auth Partner DB
        'pgsql_auth' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_AUTH', '127.0.0.1'),
            'port' => env('DB_PORT_AUTH', '5432'),
            'database' => env('DB_DATABASE_AUTH', 'tizzy_auth_partner_db'),
            'username' => env('DB_USERNAME_AUTH', 'postgres'),
            'password' => env('DB_PASSWORD_AUTH', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // 3. Order Billing DB
        'pgsql_billing' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_BILLING', '127.0.0.1'),
            'port' => env('DB_PORT_BILLING', '5432'),
            'database' => env('DB_DATABASE_BILLING', 'tizzy_order_billing_db'),
            'username' => env('DB_USERNAME_BILLING', 'postgres'),
            'password' => env('DB_PASSWORD_BILLING', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // 4. Product Service DB
        'pgsql_product' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_PRODUCT', '127.0.0.1'),
            'port' => env('DB_PORT_PRODUCT', '5432'),
            'database' => env('DB_DATABASE_PRODUCT', 'tizzy_product_service_db'),
            'username' => env('DB_USERNAME_PRODUCT', 'postgres'),
            'password' => env('DB_PASSWORD_PRODUCT', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        // 5. Support Ticket DB
        'pgsql_support' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_SUPPORT', '127.0.0.1'),
            'port' => env('DB_PORT_SUPPORT', '5432'),
            'database' => env('DB_DATABASE_SUPPORT', 'tizzy_support_ticket_db'),
            'username' => env('DB_USERNAME_SUPPORT', 'postgres'),
            'password' => env('DB_PASSWORD_SUPPORT', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
         // ✅ 6. Tizzy CRM DB (NEW)
         'pgsql_crm' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST_CRM', '127.0.0.1'),
            'port' => env('DB_PORT_CRM', '5432'),
            'database' => env('DB_DATABASE_CRM', 'tizzy_crm_db'),
            'username' => env('DB_USERNAME_CRM', 'postgres'),
            'password' => env('DB_PASSWORD_CRM', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

    ],
    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
