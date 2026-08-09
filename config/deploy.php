<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Binaries used by deploy/update.sh
    |--------------------------------------------------------------------------
    |
    | Overridable per-server since CloudPanel/PHP-FPM's PATH is minimal and
    | doesn't always resolve "composer"/"npm" the way an interactive SSH
    | shell does. php_bin defaults to the exact binary currently serving the
    | request, guaranteeing the same PHP version runs the artisan commands.
    |
    */

    'php_bin' => env('DEPLOY_PHP_BIN', PHP_BINARY),

    'composer_bin' => env('DEPLOY_COMPOSER_BIN', 'composer'),

    'npm_bin' => env('DEPLOY_NPM_BIN', 'npm'),

    /*
    |--------------------------------------------------------------------------
    | Git remote branch tracked for updates
    |--------------------------------------------------------------------------
    */

    'remote' => env('DEPLOY_GIT_REMOTE', 'origin'),

    'branch' => env('DEPLOY_GIT_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Timeout (seconds) for the update script
    |--------------------------------------------------------------------------
    |
    | composer install + npm ci + npm run build can comfortably take a
    | few minutes on a small VPS.
    |
    */

    'timeout' => (int) env('DEPLOY_TIMEOUT', 600),

];
