<?php

declare(strict_types=1);

namespace Compadres\Commerce\Infrastructure;

final class Redactor {

	private const SENSITIVE_KEYS = array(
		'api_key',
		'authorization',
		'card_number',
		'client_secret',
		'cvv',
		'document_image',
		'government_id',
		'license_key',
		'password',
		'signature_key',
		'token',
		'transaction_key',
	);

	/**
	 * @param array<string|int, mixed> $context
	 * @return array<string|int, mixed>
	 */
	public function redact( array $context ): array {
		$redacted = array();
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), self::SENSITIVE_KEYS, true ) ) {
				$redacted[ $key ] = '[REDACTED]';
				continue;
			}
			$redacted[ $key ] = is_array( $value ) ? $this->redact( $value ) : $value;
		}
		return $redacted;
	}

	public function redactMessage( string $message ): string {
		return (string) preg_replace(
			'/(Authorization:\s*Bearer)\s+[^\s]+/i',
			'$1 [REDACTED]',
			$message
		);
	}
}
