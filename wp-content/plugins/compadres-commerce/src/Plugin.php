<?php

declare(strict_types=1);

namespace Compadres\Commerce;

use Compadres\Commerce\Catalog\BrandTaxonomy;
use Compadres\Commerce\Catalog\CatalogCommand;
use Compadres\Commerce\Catalog\CatalogFilters;
use Compadres\Commerce\Catalog\FixtureCommand;
use Compadres\Commerce\Catalog\ProductMetadata;
use Compadres\Commerce\Infrastructure\Environment;
use Compadres\Commerce\Security\RoleManager;

final class Plugin {

	private Environment $environment;

	public function __construct( Environment $environment ) {
		$this->environment = $environment;
	}

	public static function boot(): void {
		$environment_name = getenv( 'APP_ENV' );
		$environment      = Environment::fromString(
			false === $environment_name ? 'production' : (string) $environment_name
		);
		$plugin           = new self( $environment );
		$plugin->registerHooks();
	}

	private function registerHooks(): void {
		add_action( 'admin_notices', array( $this, 'renderReadinessNotice' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declareHposCompatibility' ) );
		add_action( 'init', array( $this, 'ensureRoles' ), 5 );
		( new BrandTaxonomy() )->registerHooks();
		( new ProductMetadata() )->registerHooks();
		( new CatalogFilters() )->registerHooks();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'compadres fixtures', new FixtureCommand() );
			\WP_CLI::add_command( 'compadres catalog', new CatalogCommand() );
		}
	}

	public function ensureRoles(): void {
		if ( '1' === get_option( 'compadres_roles_version' ) ) {
			return;
		}
		RoleManager::install();
		update_option( 'compadres_roles_version', '1', false );
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
