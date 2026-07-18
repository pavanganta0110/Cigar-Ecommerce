<?php
/**
 * Plugin Name: Compadres Environment Hardening
 * Description: Environment-aware baseline security and cache controls.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
    $compadres_environment = getenv( 'APP_ENV' ) ?: 'production';
    $compadres_allowed     = array( 'local', 'development', 'staging', 'production' );
    define(
        'WP_ENVIRONMENT_TYPE',
        in_array( $compadres_environment, $compadres_allowed, true ) ? $compadres_environment : 'production'
    );
}

add_action(
    'send_headers',
    static function (): void {
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );

        if ( is_admin() || is_user_logged_in() || is_cart() || is_checkout() || is_account_page() ) {
            nocache_headers();
            header( 'Cache-Control: private, no-store, max-age=0', true );
        }
    }
);

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_is_application_passwords_available', '__return_false' );
