<?php

declare(strict_types=1);

namespace Compadres\Commerce\Notifications;

/**
 * Compadres-branded defaults for WooCommerce's transactional emails.
 *
 * These are registered against the `default_option_*` filter, not the
 * option itself: WordPress only calls a `default_option_{name}` filter when
 * the option has never been saved, so a store administrator who has already
 * configured Settings → WooCommerce → Emails keeps their own choice. This
 * only changes what a freshly installed store looks like before anyone has
 * touched that screen, matching the brand palette already defined in
 * wp-content/themes/compadres/theme.json.
 *
 * This does not create or override individual email templates (order
 * confirmation, processing, completed, and so on); WooCommerce's own
 * templates and content remain authoritative.
 */
final class EmailBranding {

	private const BASE_COLOR            = '#17120f';
	private const BACKGROUND_COLOR      = '#f3eadc';
	private const BODY_BACKGROUND_COLOR = '#fbf7f0';
	private const TEXT_COLOR            = '#17120f';
	private const FROM_NAME             = 'Compadres Cigars';
	private const FOOTER_TEXT           = 'Compadres Cigars — {site_title}. Adult signature required on all cigar deliveries.';

	public function registerHooks(): void {
		add_filter( 'default_option_woocommerce_email_base_color', array( $this, 'baseColor' ) );
		add_filter( 'default_option_woocommerce_email_background_color', array( $this, 'backgroundColor' ) );
		add_filter( 'default_option_woocommerce_email_body_background_color', array( $this, 'bodyBackgroundColor' ) );
		add_filter( 'default_option_woocommerce_email_text_color', array( $this, 'textColor' ) );
		add_filter( 'default_option_woocommerce_email_from_name', array( $this, 'fromName' ) );
		add_filter( 'default_option_woocommerce_email_footer_text', array( $this, 'footerText' ) );
	}

	public function baseColor(): string {
		return self::BASE_COLOR;
	}

	public function backgroundColor(): string {
		return self::BACKGROUND_COLOR;
	}

	public function bodyBackgroundColor(): string {
		return self::BODY_BACKGROUND_COLOR;
	}

	public function textColor(): string {
		return self::TEXT_COLOR;
	}

	public function fromName(): string {
		return self::FROM_NAME;
	}

	public function footerText(): string {
		return self::FOOTER_TEXT;
	}
}
