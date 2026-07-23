<?php

declare(strict_types=1);

namespace Compadres\Commerce\Audit;

final class AuditMigration {

	public const OPTION = 'compadres_audit_schema_version';

	public static function maybeInstall(): void {
		if ( AuditSchema::VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collation = $wpdb->collate ? $wpdb->collate : 'utf8mb4_unicode_ci';
		dbDelta( AuditSchema::createTableSql( $wpdb->prefix, $collation ) );
		update_option( self::OPTION, AuditSchema::VERSION, false );
	}
}
