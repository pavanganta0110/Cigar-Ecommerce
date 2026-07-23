<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

use wpdb;

final class WordPressAuditStore implements AuditStore {

	public function __construct( private wpdb $database ) {}

	/** @param array<string, mixed> $record */
	public function insert( array $record ): int|false {
		$data = $record;
		foreach ( array( 'previous_value', 'new_value', 'request_context' ) as $field ) {
			$data[ $field ] = wp_json_encode( $record[ $field ] ?? array(), JSON_UNESCAPED_SLASHES );
		}
		$inserted = $this->database->insert(
			AuditSchema::tableName( $this->database->prefix ),
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return false === $inserted ? false : (int) $this->database->insert_id;
	}

	/**
	 * @param  array<string, scalar> $filters Query filters.
	 * @return list<array<string, mixed>>
	 */
	public function search( array $filters, int $page, int $per_page ): array {
		$where  = $this->where( $filters );
		$limit  = max( 1, min( 100, $per_page ) );
		$offset = max( 0, ( $page - 1 ) * $limit );
		$table  = AuditSchema::tableName( $this->database->prefix );
		$sql    = $this->database->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY audit_id DESC LIMIT %d OFFSET %d", $limit, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and prepared WHERE fragments are generated internally.
		$rows   = $this->database->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return array_map( array( $this, 'decodeRow' ), is_array( $rows ) ? $rows : array() );
	}

	/** @param array<string, scalar> $filters */
	public function count( array $filters ): int {
		$where = $this->where( $filters );
		$table = AuditSchema::tableName( $this->database->prefix );
		return (int) $this->database->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and prepared WHERE fragments are generated internally.
	}

	/** @param array<string, scalar> $filters */
	private function where( array $filters ): string {
		$clauses = array( '1=1' );
		foreach ( array( 'event_type', 'entity_type', 'entity_id', 'result' ) as $field ) {
			if ( isset( $filters[ $field ] ) && '' !== (string) $filters[ $field ] ) {
				$clauses[] = $this->database->prepare( "{$field} = %s", (string) $filters[ $field ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Field is from a fixed allowlist.
			}
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$clauses[] = $this->database->prepare( 'user_id = %d', (int) $filters['user_id'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = $this->database->prepare( 'created_at >= %s', (string) $filters['date_from'] . ' 00:00:00' );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = $this->database->prepare( 'created_at <= %s', (string) $filters['date_to'] . ' 23:59:59' );
		}
		return implode( ' AND ', $clauses );
	}

	/**
	 * @param  array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function decodeRow( array $row ): array {
		foreach ( array( 'previous_value', 'new_value', 'request_context' ) as $field ) {
			$decoded       = json_decode( (string) ( $row[ $field ] ?? '' ), true );
			$row[ $field ] = is_array( $decoded ) ? $decoded : array();
		}
		return $row;
	}
}
