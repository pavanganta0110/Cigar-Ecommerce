<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class CheckoutWorkflow {

	public function __construct(
		private AgeVerificationService $service,
		private CheckoutGuard $guard,
		private bool $requiresDateOfBirth
	) {}

	/** @param array<string, mixed> $checkout */
	public function verify( array $checkout, string $return_url ): CheckoutDecision {
		$request = VerificationRequest::fromCheckout( $checkout, $this->requiresDateOfBirth );
		$result  = $this->service->verify( $request );
		return $this->guard->decision( $result, $return_url );
	}
}
