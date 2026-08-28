<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Payments;

use Compadres\Commerce\Infrastructure\Environment;

/**
 * Keeps the official Global Payments WooCommerce plugin's own settings in
 * sync with our environment-backed credentials, and restricts checkout to
 * the single gateway product line this store is approved for.
 *
 * The plugin registers several gateway variants at once (Heartland, Genius,
 * Transit, wallet/BNPL sub-gateways). Only Unified Payments
 * (globalpayments_gpapi) has an approved tobacco merchant account, so every
 * other variant is unconditionally hidden. Unified Payments itself is hidden
 * too until its own configuration is complete, and live mode can only ever
 * be written into the plugin's settings when the environment is production
 * and that has been explicitly approved.
 */
final class GlobalPaymentsRuntime {

	private const SETTINGS_OPTION   = 'woocommerce_globalpayments_gpapi_settings';
	private const SUPPORTED_GATEWAY = 'globalpayments_gpapi';

	private const HIDDEN_GATEWAYS = array(
		'globalpayments_heartland',
		'globalpayments_genius',
		'globalpayments_transit',
		'globalpayments_clicktopay',
		'globalpayments_googlepay',
		'globalpayments_applepay',
		'globalpayments_affirm',
		'globalpayments_clearpay',
		'globalpayments_klarna',
		'globalpayments_bankpayment',
		'globalpayments_paypal',
	);

	/**
	 * Mirrors the plugin's own `form_fields` defaults (the values a store
	 * admin would get the first time they save the settings screen). Without
	 * this, an option array we only ever partially write ourselves is
	 * missing keys the gateway class reads directly rather than through
	 * WooCommerce's own settings accessor, which throws PHP warnings on
	 * every admin page load.
	 *
	 * @var array<string, string|list<string>>
	 */
	private const SETTINGS_DEFAULTS = array(
		'enabled'                       => 'no',
		'title'                         => 'Credit Card',
		'is_production'                 => 'no',
		'transaction_region'            => 'global',
		'account_name'                  => '',
		'account_name_dropdown'         => '',
		'sandbox_app_id'                => '',
		'sandbox_app_key'               => '',
		'sandbox_account_name'          => '',
		'sandbox_account_name_dropdown' => '',
		'allow_card_saving'             => 'no',
		'payment_interface'             => 'drop_in',
		'enable_dcc'                    => 'no',
		'hpp_text'                      => 'Select Place Order to view available payment methods.',
		'enable_gpay_hpp'               => 'no',
		'enable_applepay_hpp'           => 'no',
		'debug'                         => 'no',
		'merchant_contact_url'          => '',
		'enable_three_d_secure'         => 'no',
		'payment_action'                => 'charge',
		'txn_descriptor'                => '',
		'check_avs_cvv'                 => 'yes',
		'avs_reject_conditions'         => array( 'N', 'S', 'U', 'P', 'R', 'G', 'C', 'I' ),
		'cvn_reject_conditions'         => array( 'P', '?', 'N' ),
	);

	public function __construct(
		private GlobalPaymentsConfiguration $configuration
	) {
	}

	public static function create(): self {
		$environment = Environment::fromString( (string) getenv( 'APP_ENV' ) );
		return new self( GlobalPaymentsConfiguration::fromEnvironment( $environment ) );
	}

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'syncSettings' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filterAvailableGateways' ) );
	}

	public function isConfigured(): bool {
		return $this->configuration->isConfigured();
	}

	/**
	 * Fills any field this option is still missing with the plugin's own
	 * defaults, then overwrites only the credential and mode fields this
	 * integration owns. Every other field (enabled, title, transaction_region,
	 * payment_action, three-D Secure, AVS/CVV rules, etc.) is seeded once and
	 * afterward remains entirely under the store admin's control in the
	 * plugin's own settings screen — this never overwrites a value already
	 * present there.
	 */
	public function syncSettings(): void {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = array_merge( self::SETTINGS_DEFAULTS, $settings );

		$settings['sandbox_app_id']  = $this->configuration->sandboxAppId();
		$settings['sandbox_app_key'] = $this->configuration->sandboxAppKey();

		if ( $this->configuration->hasMerchantContactUrl() ) {
			$settings['merchant_contact_url'] = $this->configuration->merchantContactUrl();
		}

		$live_ready = $this->configuration->environment()->isProduction()
			&& $this->configuration->productionApproved()
			&& $this->configuration->hasLiveCredentials();

		if ( $live_ready ) {
			$settings['app_id']        = $this->configuration->liveAppId();
			$settings['app_key']       = $this->configuration->liveAppKey();
			$settings['is_production'] = 'yes';
		} else {
			$settings['is_production'] = 'no';
		}

		$settings['payment_interface'] = 'hpp';

		update_option( self::SETTINGS_OPTION, $settings );
	}

	/**
	 * @param array<string, mixed> $gateways
	 * @return array<string, mixed>
	 */
	public function filterAvailableGateways( array $gateways ): array {
		foreach ( self::HIDDEN_GATEWAYS as $hidden ) {
			unset( $gateways[ $hidden ] );
		}
		if ( ! $this->isConfigured() ) {
			unset( $gateways[ self::SUPPORTED_GATEWAY ] );
		}
		return $gateways;
	}
}
