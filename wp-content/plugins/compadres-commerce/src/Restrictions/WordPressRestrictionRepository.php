<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;
use RuntimeException;
use wpdb;

final class WordPressRestrictionRepository implements RestrictionRepository {

	public function __construct( private wpdb $database, private RestrictionRuleHydrator $hydrator ) {
	}

	/** @return list<RestrictionRule> */
	public function activeRules( DateTimeImmutable $at ): array {
		$rules   = RestrictionSchema::rulesTableName( $this->database->prefix );
		$targets = RestrictionSchema::targetsTableName( $this->database->prefix );
		$sql     = $this->database->prepare(
			"SELECT r.id,r.name,r.country,r.blocked_message,r.priority,r.effective_at,r.expires_at,t.target_type,t.target_value
			FROM {$rules} r
			LEFT JOIN {$targets} t ON t.rule_id = r.id
			WHERE r.enabled = 1 AND r.archived_at IS NULL AND r.effective_at <= %s AND (r.expires_at IS NULL OR r.expires_at > %s)
			ORDER BY r.priority DESC, r.id ASC, t.target_type ASC, t.target_value ASC",
			$at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Prepared read must be fresh at each enforcement boundary.
		$rows = $this->database->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			throw new RuntimeException( 'Restriction rules are unavailable.' );
		}
		/** @var list<array<string, mixed>> $rows */
		return $this->hydrator->hydrate( $rows );
	}
}
