<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Audit\AuditSchema;
use PHPUnit\Framework\TestCase;

final class AuditSchemaTest extends TestCase {

	public function testSchemaContainsRequiredFieldsAndQueryIndexes(): void {
		$sql = AuditSchema::createTableSql( 'wp_', 'utf8mb4_unicode_ci' );

		self::assertStringContainsString( 'wp_compadres_audit_log', $sql );
		foreach ( array( 'event_type', 'user_id', 'entity_type', 'entity_id', 'previous_value', 'new_value', 'result', 'failure_reason', 'correlation_id', 'environment', 'request_context', 'created_at' ) as $column ) {
			self::assertStringContainsString( $column, $sql );
		}
		foreach ( array( 'KEY event_type', 'KEY user_id', 'KEY entity', 'KEY created_at', 'KEY result' ) as $index ) {
			self::assertStringContainsString( $index, $sql );
		}
	}
}
