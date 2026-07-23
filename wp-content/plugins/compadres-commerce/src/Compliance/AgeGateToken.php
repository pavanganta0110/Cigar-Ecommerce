<?php

declare(strict_types=1);

namespace Compadres\Commerce\Compliance;

final class AgeGateToken {

	public function __construct( private string $secret ) {}

	public function issue( int $now, int $lifetime_seconds ): string {
		$expiration = $now + max( 1, $lifetime_seconds );
		return $expiration . '.' . hash_hmac( 'sha256', (string) $expiration, $this->secret );
	}

	public function isValid( string $value, int $now ): bool {
		$parts = explode( '.', $value, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		$expiration = (int) $parts[0];
		if ( $expiration < $now ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', (string) $expiration, $this->secret );
		return hash_equals( $expected, $parts[1] );
	}
}
