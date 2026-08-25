<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Throwable;
use WC_Shipping_Method;

/** Live FedEx account rates with fail-closed Adult Signature eligibility. */
final class FedExShippingMethod extends WC_Shipping_Method {

	public const METHOD_ID = 'compadres_fedex_shipping';

	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );
		$this->id                 = self::METHOD_ID;
		$this->method_title       = 'Compadres FedEx Shipping';
		$this->method_description = 'Live FedEx account rates. Requires valid server-side credentials and an approved Adult Signature service.';
		$this->supports           = array( 'shipping-zones' );
		$this->enabled            = 'yes';
		$this->title              = 'FedEx';
		$this->init();
	}

	public function init(): void {
		$this->instance_form_fields = array();
	}

	/** @param array<string, mixed> $package */
	public function is_available( $package = array() ): bool {
		return ( new WordPressShippingRuntime() )->fedExMethodAllowed();
	}

	/** @param array<string, mixed> $package */
	public function calculate_shipping( $package = array() ): void {
		$runtime = new WordPressShippingRuntime();
		if ( ! $runtime->fedExMethodAllowed() ) {
			return;
		}
		$provider = $runtime->provider();
		if ( ! $provider instanceof FedExShippingProvider ) {
			return;
		}
		$context = FedExPackageContextFactory::fromPackage(
			$package,
			(string) get_option( 'woocommerce_weight_unit', 'lbs' )
		);
		try {
			$rates = $provider->rates( $context );
		} catch ( Throwable ) {
			return;
		}
		$store_currency = strtoupper( (string) get_woocommerce_currency() );
		foreach ( $rates as $rate ) {
			if ( ! $rate->supportsAdultSignature() || $store_currency !== $rate->currency() ) {
				continue;
			}
			$this->add_rate(
				array(
					'id'        => $this->buildRateId( $rate->serviceId() ),
					'label'     => $rate->label() . ' — Adult Signature Required',
					'cost'      => $rate->amount(),
					'meta_data' => array(
						'compadres_provider'    => 'fedex',
						'compadres_service'     => $rate->serviceId(),
						'compadres_supports_as' => 'yes',
					),
				)
			);
		}
	}

	private function buildRateId( string $service_id ): string {
		return $this->id . ':' . $this->instance_id . ':' . $service_id;
	}
}
