<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

final class RestrictionSchema {

	public const VERSION = '2';

	public static function rulesTableName( string $prefix ): string {
		return $prefix . 'compadres_restriction_rules';
	}

	public static function targetsTableName( string $prefix ): string {
		return $prefix . 'compadres_restriction_targets';
	}

	/** @return list<string> */
	public static function createTableSql( string $prefix, string $collation ): array {
		$rules   = self::rulesTableName( $prefix );
		$targets = self::targetsTableName( $prefix );
		return array(
			"CREATE TABLE {$rules} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
				priority int NOT NULL DEFAULT 0,
				revision bigint(20) unsigned NOT NULL DEFAULT 1,
				fixture_key varchar(100) NOT NULL DEFAULT '',
				country char(2) NOT NULL DEFAULT 'US',
				effective_at datetime NOT NULL,
				expires_at datetime NULL,
				blocked_message varchar(500) NOT NULL DEFAULT '',
				source_name varchar(191) NOT NULL DEFAULT '',
				source_url varchar(2048) NOT NULL DEFAULT '',
				review_date date NULL,
				notes text NOT NULL,
				archived_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY active_rules (enabled, archived_at, effective_at, expires_at),
				KEY priority (priority),
				KEY fixture_key (fixture_key)
			) CHARACTER SET utf8mb4 COLLATE {$collation};",
			"CREATE TABLE {$targets} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				rule_id bigint(20) unsigned NOT NULL,
				target_type varchar(32) NOT NULL,
				target_value varchar(191) NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY rule_target (rule_id, target_type, target_value),
				KEY target_lookup (target_type, target_value)
			) CHARACTER SET utf8mb4 COLLATE {$collation};",
		);
	}
}
