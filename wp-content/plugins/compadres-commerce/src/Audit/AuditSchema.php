<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

final class AuditSchema {

	public const VERSION = '1';

	public static function tableName( string $prefix ): string {
		return $prefix . 'compadres_audit_log';
	}

	public static function createTableSql( string $prefix, string $collation ): string {
		$table = self::tableName( $prefix );
		return "CREATE TABLE {$table} (
			audit_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entity_type varchar(100) NOT NULL DEFAULT '',
			entity_id varchar(191) NOT NULL DEFAULT '',
			previous_value longtext NULL,
			new_value longtext NULL,
			result varchar(32) NOT NULL,
			failure_reason text NULL,
			correlation_id varchar(191) NOT NULL,
			environment varchar(32) NOT NULL,
			request_context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (audit_id),
			KEY event_type (event_type),
			KEY user_id (user_id),
			KEY entity (entity_type, entity_id),
			KEY created_at (created_at),
			KEY result (result)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE {$collation};";
	}
}
