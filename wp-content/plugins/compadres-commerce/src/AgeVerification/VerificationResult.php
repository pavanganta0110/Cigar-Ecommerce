<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use DateTimeImmutable;
use InvalidArgumentException;

final class VerificationResult {

	public function __construct(
		private string $provider,
		private string $reference,
		private string $status,
		private string $reasonCode,
		private DateTimeImmutable $verifiedAt,
		private ?DateTimeImmutable $expiresAt = null,
		private string $manualAction = '',
		private int $reviewerId = 0,
		private ?DateTimeImmutable $manualDecidedAt = null
	) {
		if ( ! in_array( $status, VerificationStatus::all(), true ) ) {
			throw new InvalidArgumentException( 'Unsupported age-verification status.' );
		}
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,32}$/', $provider ) ) {
			throw new InvalidArgumentException( 'Invalid age-verification provider identifier.' );
		}
		if ( '' !== $reference && ( strlen( $reference ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/', $reference ) ) ) {
			throw new InvalidArgumentException( 'Invalid age-verification reference.' );
		}
		if ( strlen( $reasonCode ) > 64 || ( '' !== $reasonCode && 1 !== preg_match( '/^[a-z0-9_.-]+$/', $reasonCode ) ) ) {
			throw new InvalidArgumentException( 'Invalid age-verification reason code.' );
		}
		if ( in_array( $status, array( VerificationStatus::PASSED, VerificationStatus::PENDING, VerificationStatus::MANUAL_REVIEW ), true ) && '' === $reference ) {
			throw new InvalidArgumentException( 'Age-verification reference is required for this status.' );
		}
		if ( VerificationStatus::PASSED === $status && ( null === $expiresAt || $expiresAt <= $verifiedAt ) ) {
			throw new InvalidArgumentException( 'A passing age verification requires a future expiration.' );
		}
	}

	/** @return array{provider:string,reference:string,status:string,reason_code:string,verified_at:string,expires_at:string,manual_action:string,reviewer_id:int,manual_decided_at:string} */
	public function toArray(): array {
		return array(
			'provider'          => $this->provider,
			'reference'         => $this->reference,
			'status'            => $this->status,
			'reason_code'       => $this->reasonCode,
			'verified_at'       => $this->verifiedAt->format( DATE_ATOM ),
			'expires_at'        => null === $this->expiresAt ? '' : $this->expiresAt->format( DATE_ATOM ),
			'manual_action'     => $this->manualAction,
			'reviewer_id'       => $this->reviewerId,
			'manual_decided_at' => null === $this->manualDecidedAt ? '' : $this->manualDecidedAt->format( DATE_ATOM ),
		);
	}

	public function allowsCheckoutAt( DateTimeImmutable $now ): bool {
		return VerificationStatus::PASSED === $this->status && null !== $this->expiresAt && $this->expiresAt > $now;
	}

	public function provider(): string {
		return $this->provider;
	}

	public function status(): string {
		return $this->status;
	}

	public function reference(): string {
		return $this->reference;
	}

	public function withManualDecision( string $decision, int $reviewer_id, DateTimeImmutable $decided_at ): self {
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported manual age-verification decision.' );
		}
		$status = 'approved' === $decision ? VerificationStatus::PASSED : VerificationStatus::FAILED;
		return new self(
			$this->provider,
			$this->reference,
			$status,
			'manual_' . $decision,
			$decided_at,
			null !== $this->expiresAt && $this->expiresAt > $decided_at ? $this->expiresAt : $decided_at->modify( '+24 hours' ),
			$decision,
			$reviewer_id,
			$decided_at
		);
	}
}
