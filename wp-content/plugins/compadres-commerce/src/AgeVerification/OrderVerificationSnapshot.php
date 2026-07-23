<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class OrderVerificationSnapshot {

	private const META_MAP = array(
		'provider'          => '_compadres_age_provider',
		'reference'         => '_compadres_age_reference',
		'status'            => '_compadres_age_status',
		'verified_at'       => '_compadres_age_verified_at',
		'expires_at'        => '_compadres_age_expires_at',
		'reason_code'       => '_compadres_age_reason_code',
		'manual_action'     => '_compadres_age_manual_action',
		'reviewer_id'       => '_compadres_age_reviewer_id',
		'manual_decided_at' => '_compadres_age_manual_decided_at',
	);

	public function write( OrderMetaWriter $order, VerificationResult $result ): void {
		$data = $result->toArray();
		foreach ( self::META_MAP as $field => $meta_key ) {
			$order->set( $meta_key, $data[ $field ] );
		}
	}
}
