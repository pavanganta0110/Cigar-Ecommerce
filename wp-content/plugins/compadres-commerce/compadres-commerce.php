<?php
/**
 * Plugin Name: Compadres Commerce
 * Description: Multi-brand cigar catalog, checkout compliance, provider integrations, snapshots, reporting, and audit controls.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * WC requires at least: 10.9
 * Text Domain: compadres-commerce
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'COMPADRES_COMMERCE_VERSION', '0.1.0' );
define( 'COMPADRES_COMMERCE_FILE', __FILE__ );
define( 'COMPADRES_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Compadres' . chr( 92 ) . 'Commerce' . chr( 92 );
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = COMPADRES_COMMERCE_PATH . 'src/' . str_replace( chr( 92 ), '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'Compadres Commerce requires PHP 8.3 or newer.', 'compadres-commerce' ) );
		}
		update_option( 'compadres_commerce_schema_version', '0.1.0', false );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Compadres Commerce requires WooCommerce.', 'compadres-commerce' );
					echo '</p></div>';
				}
			);
			return;
		}
		\Compadres\Commerce\Plugin::boot();
	}
);
