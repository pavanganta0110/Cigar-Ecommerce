<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Security\RoleCapabilities;
use PHPUnit\Framework\TestCase;

final class RoleCapabilitiesTest extends TestCase {

	public function testViewerRolesCannotManageCredentialsOrRefunds(): void {
		$roles = RoleCapabilities::definitions();

		self::assertTrue( $roles['compadres_tax_finance_viewer']['capabilities']['compadres_view_tax_reports'] );
		self::assertArrayNotHasKey( 'compadres_manage_integrations', $roles['compadres_tax_finance_viewer']['capabilities'] );
		self::assertArrayNotHasKey( 'compadres_manage_refunds', $roles['compadres_marketing_viewer']['capabilities'] );
		self::assertTrue( $roles['compadres_order_manager']['capabilities']['compadres_manage_refunds'] );
	}

	public function testAuditAndComplianceCapabilitiesAreRestrictedToAuthorizedRoles(): void {
		$roles = RoleCapabilities::definitions();

		self::assertTrue( $roles['compadres_operations_manager']['capabilities']['compadres_view_audit_logs'] );
		self::assertArrayNotHasKey( 'compadres_view_audit_logs', $roles['compadres_tax_finance_viewer']['capabilities'] );
		self::assertTrue( $roles['compadres_store_administrator']['capabilities']['compadres_export_audit_logs'] );
		self::assertTrue( $roles['compadres_store_administrator']['capabilities']['compadres_manage_compliance'] );
		self::assertArrayNotHasKey( 'compadres_export_audit_logs', $roles['compadres_operations_manager']['capabilities'] );
		self::assertArrayNotHasKey( 'compadres_manage_compliance', $roles['compadres_operations_manager']['capabilities'] );
	}
}
