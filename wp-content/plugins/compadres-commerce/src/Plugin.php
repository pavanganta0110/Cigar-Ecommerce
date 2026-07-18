<?php

declare(strict_types=1);

namespace Compadres\Commerce;

use Compadres\Commerce\Infrastructure\Environment;

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
