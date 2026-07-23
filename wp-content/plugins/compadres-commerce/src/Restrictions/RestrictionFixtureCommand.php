<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use WP_CLI;

final class RestrictionFixtureCommand {

	private const KEY = 'development-product-deny-v1';

	public function load(): void {
		$this->assertAllowed();
		RestrictionMigration::install();
		$product_id = wc_get_product_id_by_sku( 'DEV-FICTIONAL-ROBUSTO-SINGLE' );
		if ( $product_id < 1 ) {
			WP_CLI::error( 'Load the fictional catalog fixtures before restriction fixtures.' );
		}
		global $wpdb;
		$rules   = RestrictionSchema::rulesTableName( $wpdb->prefix );
		$targets = RestrictionSchema::targetsTableName( $wpdb->prefix );
		$now     = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fixture transaction must be atomic.
		$wpdb->query( 'START TRANSACTION' );
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted schema helper supplies the table name.
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rules} WHERE fixture_key = %s", self::KEY ) );
			if ( $existing_id > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Development-only idempotent fixture update.
				$updated = $wpdb->update(
					$rules,
					array(
						'name'            => 'Fictional development product restriction',
						'enabled'         => 1,
						'priority'        => 100,
						'country'         => 'US',
						'effective_at'    => '2020-01-01 00:00:00',
						'expires_at'      => null,
						'blocked_message' => 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.',
						'source_name'     => 'Fictional development fixture',
						'source_url'      => '',
						'notes'           => 'FICTIONAL TEST RULE — NOT LEGAL GUIDANCE',
						'updated_at'      => $now,
					),
					array( 'id' => $existing_id )
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Targets are replaced inside the fixture transaction.
				$wpdb->delete( $targets, array( 'rule_id' => $existing_id ), array( '%d' ) );
				$rule_id = $existing_id;
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Development-only fixture creation.
				$updated = $wpdb->insert(
					$rules,
					array(
						'name'            => 'Fictional development product restriction',
						'enabled'         => 1,
						'priority'        => 100,
						'revision'        => 1,
						'fixture_key'     => self::KEY,
						'country'         => 'US',
						'effective_at'    => '2020-01-01 00:00:00',
						'expires_at'      => null,
						'blocked_message' => 'We cannot ship the current cart to this destination. Please update the shipping address or remove the restricted item.',
						'source_name'     => 'Fictional development fixture',
						'source_url'      => '',
						'notes'           => 'FICTIONAL TEST RULE — NOT LEGAL GUIDANCE',
						'created_at'      => $now,
						'updated_at'      => $now,
					)
				);
				$rule_id = (int) $wpdb->insert_id;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Development-only fixture target creation.
			$target = $wpdb->insert(
				$targets,
				array(
					'rule_id'      => $rule_id,
					'target_type'  => 'product_id',
					'target_value' => (string) $product_id,
				)
			);
			if ( false === $updated || false === $target ) {
				throw new \RuntimeException( 'Restriction fixture storage failed.' );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Completes atomic fixture load.
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $exception ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Rolls back a partial fixture load.
			$wpdb->query( 'ROLLBACK' );
			WP_CLI::error( $exception->getMessage() );
		}
		WP_CLI::success( 'Fictional development restriction fixture loaded idempotently.' );
	}

	public function remove(): void {
		$this->assertAllowed();
		global $wpdb;
		$rules   = RestrictionSchema::rulesTableName( $wpdb->prefix );
		$targets = RestrictionSchema::targetsTableName( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted schema helper supplies the table name.
		$rule_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$rules} WHERE fixture_key = %s", self::KEY ) );
		if ( $rule_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Removes only targets owned by the exact fixture rule.
			$wpdb->delete( $targets, array( 'rule_id' => $rule_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Exact primary key and ownership key scope fixture deletion.
			$wpdb->delete(
				$rules,
				array(
					'id'          => $rule_id,
					'fixture_key' => self::KEY,
				),
				array( '%d', '%s' )
			);
		}
		WP_CLI::success( 'Fictional development restriction fixture removed.' );
	}

	private function assertAllowed(): void {
		$environment = strtolower( (string) getenv( 'APP_ENV' ) );
		if ( ! in_array( $environment, array( 'local', 'development' ), true ) && ! ( 'staging' === $environment && '1' === getenv( 'COMPADRES_ENABLE_FIXTURES' ) ) ) {
			WP_CLI::error( 'Restriction fixtures are disabled in this environment.' );
		}
	}
}
