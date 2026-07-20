<?php

declare(strict_types=1);

namespace Compadres\Commerce;

use Compadres\Commerce\AgeVerification\AgeVerificationAdmin;
use Compadres\Commerce\AgeVerification\CheckoutIntegration;
use Compadres\Commerce\Audit\AuditAdmin;
use Compadres\Commerce\Audit\AuditMigration;
use Compadres\Commerce\Catalog\BrandTaxonomy;
use Compadres\Commerce\Catalog\CatalogCommand;
use Compadres\Commerce\Catalog\CatalogFilters;
use Compadres\Commerce\Catalog\FixtureCommand;
use Compadres\Commerce\Catalog\ProductMetadata;
use Compadres\Commerce\Compliance\AgeGate;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Restrictions\CheckoutRestrictionIntegration;
use Compadres\Commerce\Restrictions\RestrictionAdmin;
use Compadres\Commerce\Restrictions\RestrictionFixtureCommand;
use Compadres\Commerce\Restrictions\RestrictionMigration;
use Compadres\Commerce\Security\RoleManager;

final class Plugin {
	public const VERSION        = '0.1.0';
	private static bool $booted = false;
	private Environment $environment;

	public function __construct( Environment $environment ) {
		$this->environment = $environment;
	}

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		$environment_name = getenv( 'APP_ENV' );
		$environment      = Environment::fromString(
			false === $environment_name ? 'production' : (string) $environment_name
		);
		$plugin           = new self( $environment );
		$plugin->registerHooks();
		self::$booted = true;
	}

	private function registerHooks(): void {
		add_action( 'admin_notices', array( $this, 'renderReadinessNotice' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declareHposCompatibility' ) );
		add_action( 'init', array( AuditMigration::class, 'maybeInstall' ), 1 );
		add_action( 'init', array( RestrictionMigration::class, 'maybeInstall' ), 2 );
		add_action( 'init', array( $this, 'ensureRoles' ), 5 );
		( new AuditAdmin() )->registerHooks();
		( new BrandTaxonomy() )->registerHooks();
		( new ProductMetadata() )->registerHooks();
		( new CatalogFilters() )->registerHooks();
		( new AgeGate() )->registerHooks();
		( new CheckoutIntegration() )->registerHooks();
		( new CheckoutRestrictionIntegration() )->registerHooks();
		( new RestrictionAdmin() )->registerHooks();
		( new AgeVerificationAdmin() )->registerHooks();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'compadres fixtures', new FixtureCommand() );
			\WP_CLI::add_command( 'compadres catalog', new CatalogCommand() );
			\WP_CLI::add_command( 'compadres restriction-fixtures', new RestrictionFixtureCommand() );
		}
	}

	public function ensureRoles(): void {
		if ( '2' === get_option( 'compadres_roles_version' ) ) {
			return;
		}
		RoleManager::install();
		update_option( 'compadres_roles_version', '2', false );
	}

	public function declareHposCompatibility(): void {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				dirname( __DIR__ ) . '/compadres-commerce.php',
				true
			);
		}
	}

	public function renderReadinessNotice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( $this->environment->isProduction() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Compadres Commerce: production checkout remains unavailable until approved age, tax, payment, and shipping providers are configured.', 'compadres-commerce' );
			echo '</p></div>';
		}
	}
}
