<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use WC_Shipping_Method;

/**
 * Development-only mock WooCommerce shipping method.
 *
 * It is clearly labeled as a test method, is never carrier-approved, performs
 * no real carrier transactions, and offers no real shipping labels. It is
 * automatically unavailable in production and only available in explicitly
 * enabled staging. Its offered rates are driven by the deterministic mock
 * scenario so tests can exercise eligible, ineligible, unavailable, and
 * no-eligible-service situations.
 */
final class MockShippingMethod extends WC_Shipping_Method {

	public const METHOD_ID = 'compadres_mock_shipping';

	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->id                 = self::METHOD_ID;
		$this->method_title       = 'Compadres Test Shipping (development only)';
		$this->method_description = 'Development-only mock shipping method. Not carrier-approved. No real labels or carrier transactions.';
		$this->supports           = array( 'shipping-zones' );
		$this->enabled            = 'yes';
		$this->title              = 'Compadres Test Shipping';
		$this->init();
	}

	public function init(): void {
		$this->instance_form_fields = array();
	}

	/** @param array<string, mixed> $package */
	public function is_available( $package = array() ): bool {
		$runtime = new WordPressShippingRuntime();
		return $runtime->mockMethodAllowed();
	}

	/**
	 * Calculate rates for the mock method. No rates are returned in
	 * environments where the mock method is not permitted.
	 *
	 * @param array<string, mixed> $package Package data.
	 */
	public function calculate_shipping( $package = array() ): void {
		$runtime  = new WordPressShippingRuntime();
		$scenario = ShippingSettings::scenario();
		if ( ! $runtime->mockMethodAllowed() || ! $scenario->isProviderAvailable() ) {
			return;
		}
		foreach ( $scenario->rates() as $rate ) {
			$this->add_rate(
				array(
					'id'        => $this->buildRateId( $rate['id'] ),
					'label'     => $rate['label'],
					'cost'      => 0,
					'meta_data' => array(
						'compadres_mock_service'     => $rate['id'],
						'compadres_mock_supports_as' => $rate['supports_as'] ? 'yes' : 'no',
					),
				)
			);
		}
	}

	private function buildRateId( string $service_id ): string {
		return $this->id . ':' . $this->instance_id . ':' . $service_id;
	}
}
