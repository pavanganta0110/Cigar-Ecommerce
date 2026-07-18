<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Infrastructure\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase {

	public function test_sensitive_values_are_redacted_recursively(): void {
		$input = array(
			'event'   => 'provider.request',
			'api_key' => 'secret',
			'nested'  => array(
				'transaction_id' => 'safe-reference',
				'cvv'            => '123',
				'card_number'    => '4111111111111111',
			),
		);

		self::assertSame(
			array(
				'event'   => 'provider.request',
				'api_key' => '[REDACTED]',
				'nested'  => array(
					'transaction_id' => 'safe-reference',
					'cvv'            => '[REDACTED]',
					'card_number'    => '[REDACTED]',
				),
			),
			( new Redactor() )->redact( $input )
		);
	}

	public function test_bearer_tokens_are_redacted_inside_messages(): void {
		self::assertSame(
			'Request Authorization: Bearer [REDACTED]',
			( new Redactor() )->redactMessage( 'Request Authorization: Bearer abc.def.ghi' )
		);
	}
}
