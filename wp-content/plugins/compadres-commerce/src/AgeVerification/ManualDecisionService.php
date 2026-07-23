<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use DateTimeImmutable;
use DomainException;

final class ManualDecisionService {

	public function decide( OrderMetaStore $order, string $decision, int $reviewer_id, DateTimeImmutable $decided_at ): void {
		if ( VerificationStatus::MANUAL_REVIEW !== $order->get( '_compadres_age_status' ) || '' !== (string) $order->get( '_compadres_age_manual_action', '' ) ) {
			throw new DomainException( 'This verification is not awaiting a manual decision.' );
		}
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) || $reviewer_id < 1 ) {
			throw new DomainException( 'The manual verification decision is invalid.' );
		}
		$order->set( '_compadres_age_status', 'approved' === $decision ? VerificationStatus::PASSED : VerificationStatus::FAILED );
		$order->set( '_compadres_age_reason_code', 'manual_' . $decision );
		$order->set( '_compadres_age_manual_action', $decision );
		$order->set( '_compadres_age_reviewer_id', $reviewer_id );
		$order->set( '_compadres_age_manual_decided_at', $decided_at->format( DATE_ATOM ) );
		$order->set( '_compadres_age_verified_at', $decided_at->format( DATE_ATOM ) );
		if ( 'approved' === $decision ) {
			$order->set( '_compadres_age_expires_at', $decided_at->modify( '+24 hours' )->format( DATE_ATOM ) );
		}
	}
}
