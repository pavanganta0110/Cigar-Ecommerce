<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;

final class MockAgeVerificationProvider implements AgeVerificationProvider, DateOfBirthRequirement, RefreshableAgeVerificationProvider {

	private Closure $now;

	public function __construct(
		private bool $requiresDateOfBirth,
		callable $now,
		private string $verificationStatus = VerificationStatus::PASSED,
		private string $refreshStatus = VerificationStatus::PASSED,
		private string $hostedUrlTemplate = ''
	) {
		$this->now = Closure::fromCallable( $now );
	}

	public function name(): string {
		return 'mock';
	}

	public function requiresDateOfBirth(): bool {
		return $this->requiresDateOfBirth;
	}

	public function verify( VerificationRequest $request ): VerificationResult {
		return $this->result( $this->verificationStatus );
	}

	public function refresh( string $reference ): VerificationResult {
		return $this->result( $this->refreshStatus );
	}

	private function result( string $status ): VerificationResult {
		/** @var DateTimeImmutable $now */
		$now = ( $this->now )();
		return new VerificationResult(
			$this->name(),
			'mock-local',
			$status,
			'development_' . $status,
			$now,
			VerificationStatus::PASSED === $status ? $now->modify( '+1 day' ) : null
		);
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
