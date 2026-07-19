<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use Closure;
use DateTimeImmutable;

final class UnavailableProvider implements AgeVerificationProvider, DateOfBirthRequirement {

	private Closure $now;

	public function __construct( private string $reasonCode, private bool $requiresDateOfBirth, callable $now ) {
		$this->now = Closure::fromCallable( $now );
	}

	public function name(): string {
		return 'unconfigured';
	}

	public function requiresDateOfBirth(): bool {
		return $this->requiresDateOfBirth;
	}

	public function verify( VerificationRequest $request ): VerificationResult {
		/** @var DateTimeImmutable $now */
		$now = ( $this->now )();
		return new VerificationResult( $this->name(), '', VerificationStatus::UNAVAILABLE, $this->reasonCode, $now );
	}

	public function hostedVerificationUrl( string $reference, string $return_url ): ?string {
		return null;
	}
}
