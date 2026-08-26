<?php

declare(strict_types=1);

namespace Compadres\Commerce\Shipping;

use Compadres\Commerce\Audit\AuditServiceFactory;
use WC_Order;

/**
 * Staff entry, customer display, and admin order-list visibility for a
 * manually recorded FedEx tracking number.
 *
 * Registers both the HPOS and legacy order-list-table hooks: WordPress only
 * fires the pair matching the store's active order-storage mode, so both
 * must be registered for the column to appear regardless of that setting.
 */
final class OrderTrackingAdmin {

	private const CAPABILITY = 'edit_shop_orders';
	private const COLUMN     = 'compadres_tracking';

	public function registerHooks(): void {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'renderField' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'saveField' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'renderCustomerTracking' ) );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'addColumn' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'renderColumn' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'addColumn' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'renderLegacyColumn' ), 10, 2 );
	}

	public function renderField( WC_Order $order ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$tracking = (string) $order->get_meta( OrderTracking::META_KEY );
		wp_nonce_field( 'compadres_save_tracking_' . $order->get_id(), 'compadres_tracking_nonce' );
		?>
		<p class="form-field form-field-wide">
			<label for="compadres_tracking_number"><?php esc_html_e( 'FedEx tracking number', 'compadres-commerce' ); ?></label>
			<input type="text" id="compadres_tracking_number" name="compadres_tracking_number" maxlength="34" value="<?php echo esc_attr( $tracking ); ?>" placeholder="<?php esc_attr_e( 'e.g. 794658135984', 'compadres-commerce' ); ?>">
		</p>
		<?php
	}

	public function saveField( int $order_id ): void {
		$nonce = isset( $_POST['compadres_tracking_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['compadres_tracking_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'compadres_save_tracking_' . $order_id ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$raw       = isset( $_POST['compadres_tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['compadres_tracking_number'] ) ) : '';
		$sanitized = OrderTracking::sanitize( $raw );
		$previous  = (string) $order->get_meta( OrderTracking::META_KEY );
		if ( $sanitized === $previous ) {
			return;
		}
		if ( '' === $sanitized ) {
			$order->delete_meta_data( OrderTracking::META_KEY );
		} else {
			$order->update_meta_data( OrderTracking::META_KEY, $sanitized );
		}
		$order->save_meta_data();
		AuditServiceFactory::create()->entityChange(
			'shipping.tracking_number_updated',
			'order',
			(string) $order_id,
			array( 'tracking_number' => $previous ),
			array( 'tracking_number' => $sanitized ),
			get_current_user_id()
		);
	}

	public function renderCustomerTracking( WC_Order $order ): void {
		$tracking = (string) $order->get_meta( OrderTracking::META_KEY );
		$url      = OrderTracking::trackingUrl( $tracking );
		if ( null === $url ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Tracking', 'compadres-commerce' ); ?></h2>
		<p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $tracking ); ?></a></p>
		<?php
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function addColumn( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'Tracking', 'compadres-commerce' );
		return $columns;
	}

	public function renderColumn( string $column, WC_Order $order ): void {
		if ( self::COLUMN === $column ) {
			$this->printTrackingCell( $order );
		}
	}

	public function renderLegacyColumn( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order instanceof WC_Order ) {
			$this->printTrackingCell( $order );
		}
	}

	private function printTrackingCell( WC_Order $order ): void {
		$tracking = (string) $order->get_meta( OrderTracking::META_KEY );
		$url      = OrderTracking::trackingUrl( $tracking );
		if ( null === $url ) {
			echo '&#8212;';
			return;
		}
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $tracking ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url/esc_html applied above.
	}
}
