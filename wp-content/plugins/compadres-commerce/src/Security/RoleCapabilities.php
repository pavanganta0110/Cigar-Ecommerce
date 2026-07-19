<?php

declare(strict_types=1);

namespace Compadres\Commerce\Security;

final class RoleCapabilities {

	/** @return array<string, array{name:string,capabilities:array<string,bool>}> */
	public static function definitions(): array {
		$read = array( 'read' => true );
		return array(
			'compadres_store_administrator' => array(
				'name'         => 'Store Administrator',
				'capabilities' => $read + self::allCapabilities(),
			),
			'compadres_operations_manager'  => array(
				'name'         => 'Operations Manager',
				'capabilities' => $read + self::only( array( 'compadres_manage_orders', 'compadres_manage_inventory', 'compadres_view_customer_data', 'compadres_view_audit_logs', 'compadres_manage_restrictions' ) ),
			),
			'compadres_order_manager'       => array(
				'name'         => 'Order Manager',
				'capabilities' => $read + self::only( array( 'compadres_manage_orders', 'compadres_manage_refunds', 'compadres_view_customer_data' ) ),
			),
			'compadres_inventory_manager'   => array(
				'name'         => 'Inventory Manager',
				'capabilities' => $read + self::only( array( 'compadres_manage_inventory' ) ),
			),
			'compadres_tax_finance_viewer'  => array(
				'name'         => 'Tax and Finance Viewer',
				'capabilities' => $read + self::only( array( 'compadres_view_tax_reports', 'compadres_view_excise_reports' ) ),
			),
			'compadres_customer_service'    => array(
				'name'         => 'Customer Service',
				'capabilities' => $read + self::only( array( 'compadres_manage_orders', 'compadres_view_customer_data', 'compadres_review_age_verification' ) ),
			),
			'compadres_marketing_viewer'    => array(
				'name'         => 'Marketing Viewer',
				'capabilities' => $read + self::only( array( 'compadres_view_catalog_reports' ) ),
			),
		);
	}

	/** @return array<string, bool> */
	public static function allCapabilities(): array {
		return self::only( array( 'compadres_manage_integrations', 'compadres_view_tax_reports', 'compadres_view_excise_reports', 'compadres_view_customer_data', 'compadres_review_age_verification', 'compadres_view_audit_logs', 'compadres_export_audit_logs', 'compadres_manage_compliance', 'compadres_manage_restrictions', 'compadres_manage_refunds', 'compadres_manage_orders', 'compadres_manage_inventory', 'compadres_view_catalog_reports', 'compadres_manage_roles' ) );
	}

	/**
	 * @param  list<string> $capabilities Capability names.
	 * @return array<string, bool>
	 */
	private static function only( array $capabilities ): array {
		return array_fill_keys( $capabilities, true );
	}
}
