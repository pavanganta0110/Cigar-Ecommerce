<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Plugin;
use WC_Order;

/**
 * Displays Adult Signature Required shipping-eligibility details on the
 * WooCommerce order screen. No separate shipping dashboard is created.
 */
final class ShippingAdmin {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStyles' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render' ), 20 );
	}

	public function enqueueStyles(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/compadres-commerce.php';
		wp_enqueue_style( 'compadres-shipping-admin', plugins_url( 'assets/css/shipping-admin.css', $plugin_file ), array(), Plugin::VERSION );
	}

	public function render( WC_Order $order ): void {
		$required = $order->get_meta( OrderShippingMeta::ADULT_SIGNATURE_REQUIRED );
		if ( '' === $required ) {
			return;
		}
		$eligibility = (string) $order->get_meta( OrderShippingMeta::ELIGIBILITY );
		$provider    = (string) $order->get_meta( OrderShippingMeta::PROVIDER );
		$service     = (string) $order->get_meta( OrderShippingMeta::SERVICE );
		?>
		<div class="compadres-shipping-meta">
			<h4>Compadres Shipping</h4>
			<p>
				<strong><?php echo esc_html( 'Adult Signature Required' ); ?>:</strong>
				<?php echo esc_html( 'yes' === $required ? 'Yes' : 'No' ); ?><br />
				<strong><?php echo esc_html( 'Shipping provider' ); ?>:</strong>
				<?php echo esc_html( '' !== $provider ? $provider : '—' ); ?><br />
				<strong><?php echo esc_html( 'Shipping service' ); ?>:</strong>
				<?php echo esc_html( '' !== $service ? $service : '—' ); ?><br />
				<strong><?php echo esc_html( 'Eligibility result' ); ?>:</strong>
				<?php echo esc_html( '' !== $eligibility ? $eligibility : '—' ); ?>
			</p>
		</div>
		<?php
	}
}
