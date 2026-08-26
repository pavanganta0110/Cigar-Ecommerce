<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Compadres\Commerce\Plugin;
use WP_Admin_Bar;

/** Rebrands staff-only WordPress surfaces as the Compadres Admin Portal. */
final class AdminBranding {
	private const PORTAL_NAME = 'Compadres Cigars Admin Portal';

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'removeCoreUpdateNag' ) );
		add_action( 'admin_bar_menu', array( $this, 'removeWordPressLogo' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStyles' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueueStyles' ) );
		add_filter( 'admin_footer_text', array( $this, 'footerText' ) );
		add_filter( 'update_footer', array( $this, 'removeVersion' ), 99 );
		add_filter( 'admin_title', array( $this, 'adminTitle' ), 99, 2 );
		add_filter( 'login_title', array( $this, 'adminTitle' ), 99, 2 );
		add_filter( 'login_headerurl', array( $this, 'loginUrl' ) );
		add_filter( 'login_headertext', array( $this, 'portalName' ) );
	}

	public function removeWordPressLogo( WP_Admin_Bar $admin_bar ): void {
		$admin_bar->remove_node( 'wp-logo' );
	}

	/**
	 * Removes the "WordPress X is available" nag banner for every staff
	 * role, including administrators. Update capability and the Updates
	 * screen itself are unaffected; this only hides the ambient nag banner
	 * so the staff-facing portal reads as Compadres, not WordPress.
	 */
	public function removeCoreUpdateNag(): void {
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
	}

	public function enqueueStyles(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/compadres-commerce.php';
		wp_enqueue_style( 'compadres-admin-branding', plugins_url( 'assets/css/admin-branding.css', $plugin_file ), array(), Plugin::VERSION );
	}

	public function footerText(): string {
		return self::PORTAL_NAME;
	}

	public function removeVersion(): string {
		return '';
	}

	public function adminTitle( string $admin_title, string $title ): string {
		unset( $admin_title );
		return trim( $title ) . ' ‹ ' . self::PORTAL_NAME;
	}

	public function loginUrl(): string {
		return home_url( '/' );
	}

	public function portalName(): string {
		return self::PORTAL_NAME;
	}
}
