<?php

declare(strict_types=1);

namespace Compadres\Commerce\Admin;

use Automattic\WooCommerce\Admin\Notes\Notes;

/**
 * One-time defaults that keep WooCommerce's own onboarding wizard, "Things
 * to do next" task list, Inbox marketing notes, Marketplace upsell
 * suggestions, and the Marketing page's multichannel promo banner from ever
 * showing to staff.
 *
 * Applied once, version-gated the same way as every other installer in this
 * plugin: a store admin who later re-enables any of these from
 * WooCommerce's own settings screens is not overridden again on the next
 * page load. Existing Inbox notes are a one-time cleanup rather than an
 * ongoing filter, since new ones stop being generated once
 * AdminBranding::disableMarketingFeatures() turns the feature off.
 */
final class WooCommerceAdminDefaults {

	public const OPTION_VERSION = 'compadres_woocommerce_admin_defaults_version';
	public const VERSION        = '5';

	private const MAX_NOTE_PAGES = 10;

	public static function maybeInstall(): void {
		if ( self::VERSION === get_option( self::OPTION_VERSION ) ) {
			return;
		}

		$profile = get_option( 'woocommerce_onboarding_profile' );
		if ( ! is_array( $profile ) ) {
			$profile = array();
		}
		if ( ! isset( $profile['completed'] ) && ! isset( $profile['skipped'] ) ) {
			$profile['skipped'] = true;
			update_option( 'woocommerce_onboarding_profile', $profile );
		}

		update_option( 'woocommerce_task_list_hidden_lists', array( 'setup', 'extended', 'secret_tasklist' ) );
		update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
		update_option( 'woocommerce_marketing_overview_multichannel_banner_dismissed', 'yes' );

		if ( class_exists( Notes::class ) ) {
			for ( $page = 0; $page < self::MAX_NOTE_PAGES; $page++ ) {
				$deleted = Notes::delete_all_notes();
				if ( array() === $deleted ) {
					break;
				}
			}
		}

		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			self::dismissJetpackInstallPrompt( (int) $user_id );
		}

		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	/**
	 * Marks the Home screen's "Get traffic stats with Jetpack" prompt as
	 * already dismissed for one user, matching the per-user preference
	 * WooCommerce itself writes when a staff member clicks "No thanks".
	 * Called both here (for every existing user, once) and from
	 * AdminBranding on user_register (for every user created afterward).
	 */
	public static function dismissJetpackInstallPrompt( int $user_id ): void {
		$existing = get_user_meta( $user_id, 'woocommerce_admin_homepage_stats', true );
		$decoded  = is_string( $existing ) && '' !== $existing ? json_decode( $existing, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		if ( true === ( $decoded['installJetpackDismissed'] ?? null ) ) {
			return;
		}
		$decoded['installJetpackDismissed'] = true;
		update_user_meta( $user_id, 'woocommerce_admin_homepage_stats', wp_json_encode( $decoded ) );
	}
}
