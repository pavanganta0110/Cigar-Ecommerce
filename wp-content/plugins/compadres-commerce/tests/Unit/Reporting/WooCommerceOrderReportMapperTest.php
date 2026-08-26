<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

use Compadres\Commerce\Reporting\WooCommerceOrderReportMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WooCommerceOrderReportMapperTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! class_exists( 'WC_Order' ) ) {
			class_alias( ReportOrderTestDouble::class, 'WC_Order' );
		}
		if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
			class_alias( ReportOrderItemTestDouble::class, 'WC_Order_Item_Product' );
		}
	}

	public function test_maps_order_and_line_refunds_without_customer_data(): void {
		$product = new ReportProductTestDouble( 12, 'SKU-12', 7 );
		$item    = new ReportOrderItemTestDouble( 5, 12, 0, 'Reserva', 2.0, 100.0, 90.0, 9.0, $product );
		$order   = new ReportOrderTestDouble( $item );

		$snapshot = ( new WooCommerceOrderReportMapper() )->map( $order );

		self::assertSame( 'processing', $snapshot->status() );
		self::assertSame( 'MO', $snapshot->state() );
		self::assertSame( 100.0, $snapshot->grossSales() );
		self::assertSame( 10.0, $snapshot->discounts() );
		self::assertSame( 8.0, $snapshot->shipping() );
		self::assertSame( 2.0, $snapshot->refundedShipping() );
		self::assertSame( 9.0, $snapshot->tax() );
		self::assertSame( 1.0, $snapshot->refundedTax() );
		self::assertSame( 20.0, $snapshot->refundedSales() );
		self::assertCount( 1, $snapshot->products() );
		self::assertSame( 1.0, $snapshot->products()[0]->refundedQuantity() );
		self::assertSame( 20.0, $snapshot->products()[0]->refundedRevenue() );
		self::assertSame( 1.0, $snapshot->products()[0]->refundedTax() );
		self::assertSame( 7, $snapshot->products()[0]->stockQuantity() );
	}

	public function test_maps_variation_sales_to_the_parent_product_filter(): void {
		$variation = new ReportProductTestDouble( 34, 'SKU-12-LARGE', 4 );
		$item      = new ReportOrderItemTestDouble( 5, 12, 34, 'Reserva - Large', 1.0, 50.0, 50.0, 4.0, $variation );
		$order     = new ReportOrderTestDouble( $item );

		$snapshot = ( new WooCommerceOrderReportMapper() )->map( $order );

		self::assertSame( 12, $snapshot->products()[0]->productId() );
		self::assertSame( 'SKU-12-LARGE', $snapshot->products()[0]->sku() );
		self::assertSame( 4, $snapshot->products()[0]->stockQuantity() );
	}

	public function test_attributes_only_in_range_refunds_without_recounting_the_original_order(): void {
		$product     = new ReportProductTestDouble( 12, 'SKU-12', 7 );
		$item        = new ReportOrderItemTestDouble( 5, 12, 0, 'Reserva', 2.0, 100.0, 90.0, 9.0, $product );
		$refund_item = new ReportOrderItemTestDouble( 9, 12, 0, 'Reserva', -1.0, -20.0, -20.0, -1.0, $product, 5 );
		$order       = new ReportOrderTestDouble(
			$item,
			array(
				new ReportRefundTestDouble( '2026-07-31', $refund_item ),
				new ReportRefundTestDouble( '2026-08-19', $refund_item ),
			)
		);

		$snapshot = ( new WooCommerceOrderReportMapper() )->map(
			$order,
			new DateTimeImmutable( '2026-08-01 00:00:00' ),
			new DateTimeImmutable( '2026-08-31 23:59:59' ),
			false
		);

		self::assertFalse( $snapshot->countsAsOrder() );
		self::assertSame( 0.0, $snapshot->grossSales() );
		self::assertSame( 20.0, $snapshot->refundedSales() );
		self::assertSame( 1.0, $snapshot->refundedTax() );
		self::assertSame( 1.0, $snapshot->products()[0]->refundedQuantity() );
	}

	public function test_maps_amount_only_refunds_into_order_level_refunded_sales(): void {
		$product = new ReportProductTestDouble( 12, 'SKU-12', 7 );
		$item    = new ReportOrderItemTestDouble( 5, 12, 0, 'Reserva', 2.0, 100.0, 90.0, 9.0, $product );
		$order   = new ReportOrderTestDouble( $item, array( new ReportRefundTestDouble( '2026-08-19', null, 10.0 ) ) );

		$snapshot = ( new WooCommerceOrderReportMapper() )->map(
			$order,
			new DateTimeImmutable( '2026-08-01 00:00:00' ),
			new DateTimeImmutable( '2026-08-31 23:59:59' ),
			false
		);

		self::assertSame( 0.0, $snapshot->refundedSales() );
		self::assertSame( 10.0, $snapshot->unallocatedRefunds() );
		self::assertSame( 0.0, $snapshot->refundedTax() );
	}

	public function test_uses_immutable_tax_snapshot_instead_of_mutable_destination_values(): void {
		$product = new ReportProductTestDouble( 12, 'SKU-12', 7 );
		$item    = new ReportOrderItemTestDouble( 5, 12, 0, 'Reserva', 2.0, 100.0, 90.0, 9.0, $product );
		$order   = new ReportOrderTestDouble(
			$item,
			array(),
			array(
				'_compadres_sales_tax_rule_state' => 'IL',
				'_compadres_sales_tax_amount'     => '7.75',
			)
		);

		$snapshot = ( new WooCommerceOrderReportMapper() )->map( $order );

		self::assertSame( 'IL', $snapshot->state() );
		self::assertSame( 7.75, $snapshot->tax() );
	}
}
