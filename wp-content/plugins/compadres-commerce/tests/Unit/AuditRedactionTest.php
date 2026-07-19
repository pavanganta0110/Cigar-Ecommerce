<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Infrastructure\Redactor;
use PHPUnit\Framework\TestCase;

final class AuditRedactionTest extends TestCase {

	public function testNestedSensitiveFieldsAreRedactedWithoutChangingSafeValues(): void {
		$redacted = ( new Redactor() )->redact(
			array(
				'provider' => 'mock-age',
				'request'  => array(
					'headers'  => array(
						'Authorization' => 'Bearer secret',
						'Cookie'        => 'session=secret',
					),
					'identity' => array(
						'government_id_number' => '123456789',
						'document_image'       => 'binary-data',
					),
				),
			)
		);

		self::assertSame( 'mock-age', $redacted['provider'] );
		self::assertSame( '[REDACTED]', $redacted['request']['headers']['Authorization'] );
		self::assertSame( '[REDACTED]', $redacted['request']['headers']['Cookie'] );
		self::assertSame( '[REDACTED]', $redacted['request']['identity']['government_id_number'] );
		self::assertSame( '[REDACTED]', $redacted['request']['identity']['document_image'] );
	}
}
