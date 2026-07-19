<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

final class VerificationStatus {

	public const PASSED        = 'passed';
	public const FAILED        = 'failed';
	public const PENDING       = 'pending';
	public const MANUAL_REVIEW = 'manual_review';
	public const EXPIRED       = 'expired';
	public const UNAVAILABLE   = 'unavailable';

	/** @return list<string> */
	public static function all(): array {
		return array( self::PASSED, self::FAILED, self::PENDING, self::MANUAL_REVIEW, self::EXPIRED, self::UNAVAILABLE );
	}
}
