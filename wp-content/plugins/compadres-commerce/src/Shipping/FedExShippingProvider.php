<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/** Production FedEx adapter for rate and Adult Signature eligibility checks. */
final class FedExShippingProvider implements ShippingMethodProvider {

	/** @var array<string, list<FedExRateQuote>> */
	private array $rates_by_context = array();

	/** @var array<string, FedExRateQuote> */
	private array $current_rates = array();

	public function __construct(
		private FedExConfiguration $configuration,
		private FedExApiClient $client
	) {
	}

	public function isConfigured(): bool {
		return $this->configuration->isConfigured();
	}

	/** @return list<string> */
	public function eligibleServices( ShippingContext $context ): array {
		$services = array();
		foreach ( $this->rates( $context ) as $rate ) {
			if ( $rate->supportsAdultSignature() ) {
				$services[] = $rate->serviceId();
			}
		}
		return $services;
	}

	public function supportsAdultSignature( string $service_id ): bool {
		$service_id = strtolower( $service_id );
		return isset( $this->current_rates[ $service_id ] )
			&& $this->current_rates[ $service_id ]->supportsAdultSignature();
	}

	public function providerName(): string {
		return 'fedex';
	}

	public function serviceReference( string $service_id ): ?string {
		$service_id = strtolower( $service_id );
		return isset( $this->current_rates[ $service_id ] )
			? $this->current_rates[ $service_id ]->reference()
			: null;
	}

	/** @return list<FedExRateQuote> */
	public function rates( ShippingContext $context ): array {
		if ( ! $this->isConfigured() ) {
			$this->current_rates = array();
			return array();
		}
		$key = hash(
			'sha256',
			implode(
				'|',
				array(
					$context->country(),
					$context->state(),
					$context->postalCode(),
					(string) $context->weight(),
					$context->weightUnit(),
				)
			)
		);
		if ( ! isset( $this->rates_by_context[ $key ] ) ) {
			$this->rates_by_context[ $key ] = $this->client->rates( $context );
		}
		$this->current_rates = array();
		foreach ( $this->rates_by_context[ $key ] as $rate ) {
			$this->current_rates[ $rate->serviceId() ] = $rate;
		}
		return $this->rates_by_context[ $key ];
	}
}
