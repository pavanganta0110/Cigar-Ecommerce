<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;

/**
 * Checkout age verification by customer self-attestation only: a single
 * required checkbox, no date of birth, no third-party identity check.
 *
 * This is a materially weaker compliance posture than a real verification
 * provider and is a business/legal decision, not a technical one; see
 * docs/compliance.md. VerificationRequest::fromCheckout() throws before
 * this provider is ever invoked if the checkbox was not checked, so
 * reaching verify() at all already means the required confirmation was
 * given — the same design already used by the mock provider, which also
 * does not need to inspect the request.
 */
final class SelfAttestationProvider implements AgeVerificationProvider, AttestationRequirement {

	private Closure $now;
	private Closure $referenceGenerator;

	public function __construct( callable $now, ?callable $reference_generator = null ) {
		$this->now                = Closure::fromCallable( $now );
		$this->referenceGenerator = Closure::fromCallable( $reference_generator ?? static fn (): string => wp_generate_uuid4() );
	}

	public function name(): string {
		return 'self_attestation';
	}

	public function requiresAttestation(): bool {
		return true;
	}

	public function verify( VerificationRequest $request ): VerificationResult {
		unset( $request );
		/** @var DateTimeImmutable $now */
		$now = ( $this->now )();
		/** @var string $reference */
		$reference = ( $this->referenceGenerator )();
		return new VerificationResult(
			$this->name(),
			'self-attested-' . $reference,
			VerificationStatus::PASSED,
			'checkbox_confirmed',
			$now,
			$now->modify( '+1 day' )
		);
	}

	public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
		unset( $reference, $return_url );
		return null;
	}
}
