<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

final class RestrictionMigration {

	public const OPTION = 'compadres_restriction_schema_version';

	public static function maybeInstall(): void {
		if ( RestrictionSchema::VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collation = $wpdb->collate ? $wpdb->collate : 'utf8mb4_unicode_ci';
		foreach ( RestrictionSchema::createTableSql( $wpdb->prefix, $collation ) as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::OPTION, RestrictionSchema::VERSION, false );
	}
}
