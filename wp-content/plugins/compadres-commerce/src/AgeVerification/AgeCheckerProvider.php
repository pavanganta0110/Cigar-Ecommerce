<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;
use Throwable;

final class AgeCheckerProvider implements AgeVerificationProvider, DateOfBirthRequirement, RefreshableAgeVerificationProvider {

	private Closure $now;

	public function __construct(
		private AgeCheckerTransport $transport,
		private string $hostedUrlTemplate,
		callable $now,
		private bool $requiresDateOfBirth = false
	) {
		$this->now = Closure::fromCallable( $now );
	}

	public function name(): string {
		return 'agechecker';
	}

	public function requiresDateOfBirth(): bool {
		return $this->requiresDateOfBirth;
	}

	public function verify( VerificationRequest $request ): VerificationResult {
		try {
			$response = $this->transport->verify( $request->providerData() );
			return $this->normalize( $response );
		} catch ( Throwable ) {
			return $this->unavailable( '' );
		}
	}

	public function refresh( string $reference ): VerificationResult {
		if ( ! $this->transport instanceof AgeCheckerStatusTransport || '' === $reference ) {
			return $this->unavailable( $reference );
		}
		try {
			return $this->normalize( $this->transport->status( $reference ) );
		} catch ( Throwable ) {
			return $this->unavailable( $reference );
		}
	}

	/** @param array<string, string> $response */
	private function normalize( array $response ): VerificationResult {
		$status = isset( $response['status'] ) && in_array( $response['status'], VerificationStatus::all(), true )
			? $response['status']
			: VerificationStatus::UNAVAILABLE;
		/** @var DateTimeImmutable $now */
		$now         = ( $this->now )();
		$verified_at = isset( $response['verified_at'] ) && '' !== $response['verified_at'] ? new DateTimeImmutable( $response['verified_at'] ) : $now;
		$expires_at  = isset( $response['expires_at'] ) && '' !== $response['expires_at'] ? new DateTimeImmutable( $response['expires_at'] ) : null;
		return new VerificationResult(
			$this->name(),
			isset( $response['reference'] ) ? $response['reference'] : '',
			$status,
			isset( $response['reason_code'] ) ? $response['reason_code'] : 'provider_response_invalid',
			$verified_at,
			$expires_at
		);
	}

	private function unavailable( string $reference ): VerificationResult {
		/** @var DateTimeImmutable $failed_at */
		$failed_at = ( $this->now )();
		return new VerificationResult( $this->name(), $reference, VerificationStatus::UNAVAILABLE, 'provider_unavailable', $failed_at );
	}

	public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
		if ( '' === $reference || '' === $this->hostedUrlTemplate ) {
			return null;
		}
		return str_replace(
			array( '{reference}', '{return_url}' ),
			array( rawurlencode( $reference ), rawurlencode( $return_url ) ),
			$this->hostedUrlTemplate
		);
	}
}
