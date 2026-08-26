<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Payments;

use Compadres\Commerce\Audit\AuditServiceFactory;
use WC_Order;
use WC_Order_Refund;

/**
 * Records a bounded audit trail entry whenever a refund is successfully
 * created against an order.
 *
 * The refund transaction itself is not implemented here. WooCommerce's own
 * refund flow calls the active gateway's own `process_refund()` — Global
 * Payments' own implementation, for `globalpayments_gpapi` — before
 * `woocommerce_order_refunded` fires, so by the time this hook runs the
 * money movement, if any, has already succeeded, or the refund was a
 * store-credit-only adjustment with no gateway call at all. This class adds
 * only the audit trail entry, matching every other compliance-relevant
 * admin action already logged in this project.
 */
final class PaymentAudit {

	public function registerHooks(): void {
		add_action( 'woocommerce_order_refunded', array( $this, 'recordRefund' ), 10, 2 );
	}

	public function recordRefund( int $order_id, int $refund_id ): void {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $order instanceof WC_Order || ! $refund instanceof WC_Order_Refund ) {
			return;
		}

		AuditServiceFactory::create()->success(
			'payments.refund_recorded',
			get_current_user_id(),
			'order',
			(string) $order_id,
			array(
				'refund_id' => $refund_id,
				'amount'    => $refund->get_amount(),
				'currency'  => $refund->get_currency(),
				'reason'    => mb_substr( (string) $refund->get_reason(), 0, 500 ),
				'gateway'   => $order->get_payment_method(),
			)
		);
	}
}
