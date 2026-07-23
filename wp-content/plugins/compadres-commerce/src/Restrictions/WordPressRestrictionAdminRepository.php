<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;
use wpdb;

final class WordPressRestrictionAdminRepository {

	public function __construct( private wpdb $database ) {
	}

	/** @return list<array<string, mixed>> */
	public function all(): array {
		$rules = RestrictionSchema::rulesTableName( $this->database->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Trusted schema helper supplies the table name for an admin read.
		$rows = $this->database->get_results( "SELECT * FROM {$rules} ORDER BY archived_at IS NOT NULL ASC, priority DESC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			throw new DomainException( 'Restriction rules are unavailable.' );
		}
		return $rows;
	}

	/** @return array<string, mixed> */
	public function find( int $rule_id ): array {
		$rules   = RestrictionSchema::rulesTableName( $this->database->prefix );
		$targets = RestrictionSchema::targetsTableName( $this->database->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Trusted schema helper supplies the table name.
		$rule = $this->database->get_row( $this->database->prepare( "SELECT * FROM {$rules} WHERE id = %d", $rule_id ), ARRAY_A );
		if ( ! is_array( $rule ) ) {
			throw new DomainException( 'Restriction rule was not found.' );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Trusted schema helper supplies the table name.
		$rows = $this->database->get_results( $this->database->prepare( "SELECT target_type,target_value FROM {$targets} WHERE rule_id = %d ORDER BY target_type,target_value", $rule_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			throw new DomainException( 'Restriction targets are unavailable.' );
		}
		$rule['targets'] = array();
		foreach ( $rows as $row ) {
			$type                       = (string) ( $row['target_type'] ?? '' );
			$rule['targets'][ $type ]   = $rule['targets'][ $type ] ?? array();
			$rule['targets'][ $type ][] = (string) ( $row['target_value'] ?? '' );
		}
		return $rule;
	}

	public function create( RestrictionRuleInput $input ): int {
		$rules = RestrictionSchema::rulesTableName( $this->database->prefix );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$this->begin();
		try {
			$data               = $input->rule();
			$data['revision']   = 1;
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Parent and targets are written atomically.
			if ( false === $this->database->insert( $rules, $data ) ) {
				throw new DomainException( 'Restriction rule could not be created.' );
			}
			$rule_id = (int) $this->database->insert_id;
			$this->replaceTargets( $rule_id, $input->targets() );
			$this->commit();
			return $rule_id;
		} catch ( Throwable $exception ) {
			$this->rollback();
			throw $exception;
		}
	}

	public function update( int $rule_id, int $revision, RestrictionRuleInput $input ): void {
		$rules = RestrictionSchema::rulesTableName( $this->database->prefix );
		$this->begin();
		try {
			$data               = $input->rule();
			$data['revision']   = $revision + 1;
			$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Revision-qualified compare-and-swap prevents lost updates.
			$updated = $this->database->update(
				$rules,
				$data,
				array(
					'id'          => $rule_id,
					'revision'    => $revision,
					'archived_at' => null,
				)
			);
			if ( 1 !== $updated ) {
				throw new DomainException( 'Restriction rule changed in another request or is archived.' );
			}
			$this->replaceTargets( $rule_id, $input->targets() );
			$this->commit();
		} catch ( Throwable $exception ) {
			$this->rollback();
			throw $exception;
		}
	}

	public function setEnabled( int $rule_id, int $revision, bool $enabled ): void {
		$rules = RestrictionSchema::rulesTableName( $this->database->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Revision-qualified compare-and-swap prevents lost updates.
		$updated = $this->database->update(
			$rules,
			array(
				'enabled'    => $enabled ? 1 : 0,
				'revision'   => $revision + 1,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'id'          => $rule_id,
				'revision'    => $revision,
				'archived_at' => null,
			)
		);
		if ( 1 !== $updated ) {
			throw new DomainException( 'Restriction rule changed in another request or is archived.' );
		}
	}

	public function archive( int $rule_id, int $revision ): void {
		$rules = RestrictionSchema::rulesTableName( $this->database->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Archive preserves historical rule references and uses compare-and-swap.
		$updated = $this->database->update(
			$rules,
			array(
				'enabled'     => 0,
				'archived_at' => gmdate( 'Y-m-d H:i:s' ),
				'revision'    => $revision + 1,
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'id'          => $rule_id,
				'revision'    => $revision,
				'archived_at' => null,
			)
		);
		if ( 1 !== $updated ) {
			throw new DomainException( 'Restriction rule changed in another request or is already archived.' );
		}
	}

	/** @param array<string, list<string>> $targets */
	private function replaceTargets( int $rule_id, array $targets ): void {
		$table = RestrictionSchema::targetsTableName( $this->database->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Target replacement is enclosed in the parent transaction.
		if ( false === $this->database->delete( $table, array( 'rule_id' => $rule_id ), array( '%d' ) ) ) {
			throw new DomainException( 'Restriction targets could not be replaced.' );
		}
		foreach ( $targets as $type => $values ) {
			foreach ( $values as $value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Target replacement is enclosed in the parent transaction.
				if ( false === $this->database->insert(
					$table,
					array(
						'rule_id'      => $rule_id,
						'target_type'  => $type,
						'target_value' => $value,
					)
				) ) {
					throw new DomainException( 'Restriction target could not be stored.' );
				}
			}
		}
	}

	private function begin(): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit transaction prevents torn parent/target writes.
		$this->database->query( 'START TRANSACTION' );
	}

	private function commit(): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit transaction prevents torn parent/target writes.
		$this->database->query( 'COMMIT' );
	}

	private function rollback(): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Roll back any partial parent/target write.
		$this->database->query( 'ROLLBACK' );
	}

	public static function localDateTime( mixed $value, DateTimeZone $timezone ): string {
		if ( empty( $value ) ) {
			return '';
		}
		return ( new DateTimeImmutable( (string) $value, new DateTimeZone( 'UTC' ) ) )->setTimezone( $timezone )->format( 'Y-m-d\TH:i' );
	}
}
