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
		add_action( 'admin_init', array( $this, 'removeActionSchedulerNag' ) );
		add_action( 'admin_menu', array( $this, 'renameWooCommerceMenu' ), 999 );
		add_action( 'admin_menu', array( $this, 'removeUnusedAdminMenus' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'removeWordPressLogo' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'removeAdminBarComments' ), 999 );
		add_filter( 'woocommerce_admin_get_feature_config', array( $this, 'disableMarketingFeatures' ) );
		add_action( 'user_register', array( WooCommerceAdminDefaults::class, 'dismissJetpackInstallPrompt' ) );
		add_filter( 'rest_request_after_callbacks', array( $this, 'suppressMarketingRecommendations' ), 10, 3 );
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

	public function removeAdminBarComments( WP_Admin_Bar $admin_bar ): void {
		$admin_bar->remove_node( 'comments' );
	}

	/**
	 * Removes the Posts, Media, Comments, Pages, and Appearance menu items,
	 * and the Dashboard's Updates submenu. This store runs as pure
	 * e-commerce; none of WordPress's blogging/content scaffolding or
	 * theme-customization screens are used day to day, and update
	 * availability is still visible inline on the Plugins screen without a
	 * dedicated sidebar entry. Page editing remains reachable via the
	 * "Edit Page" link, and theme customization via the "Customize" link,
	 * that WordPress already shows in the front-end admin bar when viewing
	 * the site while logged in — so this only removes navigation paths
	 * from the sidebar, not the underlying post types, capabilities,
	 * content, themes, or update mechanism.
	 */
	public function removeUnusedAdminMenus(): void {
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'upload.php' );
		remove_menu_page( 'edit-comments.php' );
		remove_menu_page( 'edit.php?post_type=page' );
		remove_menu_page( 'themes.php' );
		remove_submenu_page( 'index.php', 'update-core.php' );
	}

	/**
	 * Relabels WooCommerce's own top-level admin menu item and swaps its
	 * logo for a neutral dashicon, so the sidebar reads as a Compadres
	 * store section rather than the WooCommerce brand. The underlying
	 * page (wc-admin Home, plus Orders/Customers/Reports/Settings/Status/
	 * Extensions beneath it) and every capability check are untouched;
	 * this only changes the label and icon shown to staff.
	 */
	public function renameWooCommerceMenu(): void {
		global $menu;
		if ( ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $index => $item ) {
			if ( isset( $item[2] ) && 'woocommerce' === $item[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Relabeling an existing top-level admin menu entry is WordPress's own documented mechanism; there is no filter for it.
				$menu[ $index ][0] = __( 'Store', 'compadres-commerce' );
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same as above.
				$menu[ $index ][6] = 'dashicons-store';
			}
		}
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

	/**
	 * Removes WooCommerce's Action Scheduler "past-due actions" nag banner
	 * for every staff role. Action Scheduler itself, and the underlying
	 * scheduled tasks it runs, are untouched — this only hides an internal
	 * library's own admin notice, which staff have no action to take on and
	 * which otherwise reads as generic WordPress/WooCommerce plumbing rather
	 * than anything about the store.
	 */
	public function removeActionSchedulerNag(): void {
		if ( ! class_exists( '\ActionScheduler_AdminView' ) ) {
			return;
		}
		remove_action( 'admin_notices', array( \ActionScheduler_AdminView::instance(), 'maybe_check_pastdue_actions' ) );
	}

	/**
	 * Turns off WooCommerce Admin's own marketing and cross-sell surfaces —
	 * the Inbox's promotional notes, remote extension suggestions, payment
	 * gateway suggestions, mobile-app banners, WooCommerce Payments
	 * promotion, shipping-label promotion, the pre-launch banner, and the
	 * post-task effort survey — without touching any feature staff actually
	 * use (Analytics, Marketing tools, Coupons). Onboarding/task-list
	 * visibility is controlled separately by stored options, not this flag
	 * list, and is unaffected.
	 *
	 * @param array<string, bool> $features
	 * @return array<string, bool>
	 */
	public function disableMarketingFeatures( array $features ): array {
		foreach (
			array(
				'remote-inbox-notifications',
				'remote-free-extensions',
				'payment-gateway-suggestions',
				'mobile-app-banner',
				'wc-pay-promotion',
				'wc-pay-welcome-page',
				'woo-mobile-welcome',
				'shipping-label-banner',
				'launch-your-store',
				'customer-effort-score-tracks',
			) as $feature
		) {
			$features[ $feature ] = false;
		}
		return $features;
	}

	/**
	 * Empties WooCommerce's own "recommended marketing channels/extensions"
	 * REST response — the Google/Reddit/TikTok "for WooCommerce" upsell
	 * cards shown on the Marketing > Overview page. There is no dedicated
	 * filter on this data, so this intercepts the specific REST route by
	 * name; every other route, including Coupons and the rest of the
	 * Marketing feature, is untouched.
	 *
	 * @param mixed             $response
	 * @param mixed             $handler
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function suppressMarketingRecommendations( $response, $handler, \WP_REST_Request $request ) {
		unset( $handler );
		if ( '/wc-admin/marketing/recommendations' === $request->get_route() ) {
			return rest_ensure_response( array() );
		}
		return $response;
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
