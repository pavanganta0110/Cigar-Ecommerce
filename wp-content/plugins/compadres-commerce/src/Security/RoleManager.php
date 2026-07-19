<?php

declare(strict_types=1);

namespace Compadres\Commerce\Security;

final class RoleManager {

	public static function install(): void {
		foreach ( RoleCapabilities::definitions() as $slug => $definition ) {
			$capabilities = $definition['capabilities'] + self::wordpressCapabilities( $slug );
			$role         = get_role( $slug );
			if ( ! $role ) {
				add_role( $slug, $definition['name'], $capabilities );
				continue;
			}
			foreach ( $capabilities as $capability => $grant ) {
				$role->add_cap( $capability, $grant );
			}
		}
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( RoleCapabilities::allCapabilities() as $capability => $grant ) {
				$administrator->add_cap( $capability, $grant );
			}
		}
	}

	/** @return array<string, bool> */
	private static function wordpressCapabilities( string $slug ): array {
		return match ( $slug ) {
			'compadres_store_administrator' => array(
				'manage_woocommerce'       => true,
				'edit_products'            => true,
				'edit_others_products'     => true,
				'publish_products'         => true,
				'read_private_products'    => true,
				'edit_shop_orders'         => true,
				'read_private_shop_orders' => true,
			),
			'compadres_operations_manager' => array(
				'manage_woocommerce'   => true,
				'edit_products'        => true,
				'edit_others_products' => true,
				'edit_shop_orders'     => true,
			),
			'compadres_order_manager', 'compadres_customer_service' => array(
				'edit_shop_orders'         => true,
				'read_private_shop_orders' => true,
			),
			'compadres_inventory_manager' => array(
				'edit_products'        => true,
				'edit_others_products' => true,
			),
			default => array(),
		};
	}
}
