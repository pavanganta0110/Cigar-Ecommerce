<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Restrictions\RestrictionSchema;
use PHPUnit\Framework\TestCase;

final class RestrictionSchemaTest extends TestCase {

	public function testSchemaDefinesVersionedRulesAndNormalizedTargets(): void {
		$sql = RestrictionSchema::createTableSql( 'wp_', 'utf8mb4_unicode_ci' );

		self::assertCount( 2, $sql );
		self::assertStringContainsString( 'wp_compadres_restriction_rules', $sql[0] );
		foreach ( array( 'name', 'enabled', 'priority', 'revision', 'fixture_key', 'country', 'effective_at', 'expires_at', 'blocked_message', 'source_name', 'source_url', 'review_date', 'notes', 'archived_at' ) as $column ) {
			self::assertStringContainsString( $column, $sql[0] );
		}
		self::assertSame( '2', RestrictionSchema::VERSION );
		self::assertStringContainsString( 'wp_compadres_restriction_targets', $sql[1] );
		foreach ( array( 'rule_id', 'target_type', 'target_value', 'UNIQUE KEY rule_target' ) as $column ) {
			self::assertStringContainsString( $column, $sql[1] );
		}
	}
}
