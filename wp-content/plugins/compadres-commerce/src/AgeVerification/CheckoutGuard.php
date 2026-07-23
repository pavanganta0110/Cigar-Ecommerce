<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;

final class CheckoutGuard {

	private Closure $now;

	public function __construct( private AgeVerificationProvider $provider, callable $now ) {
		$this->now = Closure::fromCallable( $now );
	}

	public function decision( VerificationResult $result, string $return_url ): CheckoutDecision {
		/** @var DateTimeImmutable $now */
		$now = ( $this->now )();
		if ( $result->allowsCheckoutAt( $now ) ) {
			return new CheckoutDecision( true );
		}
		$messages   = array(
			VerificationStatus::FAILED        => 'We could not verify that you are at least 21 years old.',
			VerificationStatus::PENDING       => 'Age verification is still pending. Complete verification before placing the order.',
			VerificationStatus::MANUAL_REVIEW => 'Age verification requires additional review before checkout can continue.',
			VerificationStatus::EXPIRED       => 'Your age verification has expired. Please verify again.',
			VerificationStatus::UNAVAILABLE   => 'Age verification is temporarily unavailable. Checkout cannot continue.',
		);
		$hosted_url = in_array( $result->status(), array( VerificationStatus::PENDING, VerificationStatus::MANUAL_REVIEW ), true )
			? $this->provider->hostedVerificationUrl( $result->reference(), $return_url )
			: null;
		return new CheckoutDecision( false, $messages[ $result->status() ] ?? 'Age verification must pass before checkout can continue.', $hosted_url );
	}
}
