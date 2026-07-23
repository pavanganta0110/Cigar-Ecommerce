<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Audit\AuditService;
use Compadres\Commerce\Audit\AuditStore;
use Compadres\Commerce\Audit\ChangedValues;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Infrastructure\Redactor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase {

	public function testChangedValuesExcludeUnchangedSettings(): void {
		$changes = ChangedValues::between(
			array(
				'enabled' => true,
				'title'   => 'Adults only',
				'api_key' => 'old-secret',
			),
			array(
				'enabled' => true,
				'title'   => 'Welcome',
				'api_key' => 'new-secret',
			)
		);

		self::assertSame(
			array(
				'title'   => 'Adults only',
				'api_key' => 'old-secret',
			),
			$changes['previous']
		);
		self::assertSame(
			array(
				'title'   => 'Welcome',
				'api_key' => 'new-secret',
			),
			$changes['current']
		);
		self::assertTrue( $changes['changed'] );
		self::assertFalse( ChangedValues::between( array( 'enabled' => true ), array( 'enabled' => true ) )['changed'] );
	}

	public function testSuccessfulEntityChangeIsRedactedAndStoredWithOperationalContext(): void {
		$store   = new class() implements AuditStore {
			/** @var list<array<string, mixed>> */
			public array $records = array();

			/** @param array<string, mixed> $record */
			public function insert( array $record ): int|false {
				$this->records[] = $record;
				return count( $this->records );
			}
		};
		$service = new AuditService(
			$store,
			new Redactor(),
			Environment::fromString( 'staging' ),
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 01:00:00+00:00' ),
			static fn (): string => 'correlation-123'
		);

		$audit_id = $service->entityChange(
			'age_gate.settings_updated',
			'option',
			'compadres_age_gate',
			array(
				'enabled' => false,
				'api_key' => 'old-secret',
			),
			array(
				'enabled' => true,
				'api_key' => 'new-secret',
			),
			7,
			array(
				'route'  => '/wp-admin/options.php',
				'Cookie' => 'private',
			)
		);

		self::assertSame( 1, $audit_id );
		self::assertSame( '[REDACTED]', $store->records[0]['previous_value']['api_key'] );
		self::assertSame( '[REDACTED]', $store->records[0]['new_value']['api_key'] );
		self::assertSame( '[REDACTED]', $store->records[0]['request_context']['Cookie'] );
		self::assertSame( 'success', $store->records[0]['result'] );
		self::assertSame( 'correlation-123', $store->records[0]['correlation_id'] );
		self::assertSame( 'staging', $store->records[0]['environment'] );
		self::assertSame( '2026-07-19 01:00:00', $store->records[0]['created_at'] );
	}

	public function testStorageFailureIsReportedWithoutBreakingTheCaller(): void {
		$store    = new class() implements AuditStore {
			/** @param array<string, mixed> $record */
			public function insert( array $record ): int|false {
				return false;
			}
		};
		$reported = array();
		$service  = new AuditService(
			$store,
			new Redactor(),
			Environment::fromString( 'development' ),
			static fn (): DateTimeImmutable => new DateTimeImmutable( '2026-07-19 01:00:00+00:00' ),
			static fn (): string => 'correlation-failed',
			static function ( string $message ) use ( &$reported ): void {
				$reported[] = $message;
			}
		);

		$result = $service->failure( 'audit.test', 'Authorization: Bearer private-token', 0 );

		self::assertFalse( $result );
		self::assertCount( 1, $reported );
		self::assertStringNotContainsString( 'private-token', $reported[0] );
	}
}
