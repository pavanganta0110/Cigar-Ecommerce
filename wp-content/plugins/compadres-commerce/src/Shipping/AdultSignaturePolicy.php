<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Throwable;

/** Centralized fail-closed Adult Signature Required shipping policy. */
final class AdultSignaturePolicy {

	public function requiresAdultSignature( ShippingContext $context ): bool {
		return count( $context->productIds() ) > 0;
	}

	public function evaluate( ShippingContext $context, ShippingMethodProvider $provider ): ShippingEligibilityResult {
		$requires = $this->requiresAdultSignature( $context );
		$selected = $this->normalizeIdentifier( $context->selectedServiceId() );

		try {
			return $this->evaluateProvider( $context, $provider, $requires, $selected );
		} catch ( Throwable ) {
			return ShippingEligibilityResult::blocked(
				$requires,
				false,
				$selected,
				false,
				ShippingEligibilityResult::REASON_PROVIDER_UNAVAILABLE,
				$this->audit( $provider, $selected, false )
			);
		}
	}

	private function evaluateProvider(
		ShippingContext $context,
		ShippingMethodProvider $provider,
		bool $requires,
		string $selected
	): ShippingEligibilityResult {
		if ( ! $provider->isConfigured() ) {
			return ShippingEligibilityResult::blocked(
				$requires,
				false,
				$selected,
				false,
				ShippingEligibilityResult::REASON_PROVIDER_UNAVAILABLE,
				$this->audit( $provider, $selected, false )
			);
		}

		if ( '' === $selected ) {
			$reason = array() === $provider->eligibleServices( $context )
				? ShippingEligibilityResult::REASON_NO_ELIGIBLE_SERVICE
				: ShippingEligibilityResult::REASON_NO_SERVICE_SELECTED;
			return ShippingEligibilityResult::blocked(
				$requires,
				true,
				$selected,
				false,
				$reason,
				$this->audit( $provider, $selected, false )
			);
		}

		$supports = $provider->supportsAdultSignature( $selected );
		if ( ! $supports ) {
			return ShippingEligibilityResult::blocked(
				$requires,
				true,
				$selected,
				false,
				ShippingEligibilityResult::REASON_SERVICE_UNSUPPORTED,
				$this->audit( $provider, $selected, false )
			);
		}

		if ( ! in_array( $selected, $provider->eligibleServices( $context ), true ) ) {
			return ShippingEligibilityResult::blocked(
				$requires,
				true,
				$selected,
				$supports,
				ShippingEligibilityResult::REASON_NO_ELIGIBLE_SERVICE,
				$this->audit( $provider, $selected, $supports )
			);
		}

		return ShippingEligibilityResult::allowed(
			$requires,
			true,
			$selected,
			$supports,
			$this->audit( $provider, $selected, $supports )
		);
	}

	/** @return array<string, string> */
	private function audit( ShippingMethodProvider $provider, string $service_id, bool $supports ): array {
		try {
			$provider_name = $provider->providerName();
		} catch ( Throwable ) {
			$provider_name = 'unavailable';
		}
		return array(
			'provider'    => $this->normalizeIdentifier( $provider_name ),
			'service'     => $this->normalizeIdentifier( $service_id ),
			'supports_as' => $supports ? 'yes' : 'no',
		);
	}

	private function normalizeIdentifier( string $value ): string {
		$normalized = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );
		return substr( is_string( $normalized ) ? $normalized : '', 0, 64 );
	}
}
