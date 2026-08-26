<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Reporting;

use Compadres\Commerce\Reporting\OrderReportSnapshot;
use Compadres\Commerce\Reporting\ProductReportSnapshot;
use Compadres\Commerce\Reporting\SalesTaxReport;
use PHPUnit\Framework\TestCase;

final class SalesTaxReportTest extends TestCase {

	public function test_aggregates_finalized_sales_refunds_tax_by_state_and_product(): void {
		$orders = array(
			new OrderReportSnapshot(
				'processing',
				'MO',
				100.00,
				10.00,
				8.00,
				2.00,
				9.00,
				1.00,
				20.00,
				array(
					new ProductReportSnapshot( 10, 'SKU-10', 'Reserva', 2.0, 100.00, 90.00, 9.00, 1.0, 20.00, 1.00, 12 ),
				)
			),
			new OrderReportSnapshot(
				'completed',
				'IL',
				50.00,
				0.00,
				5.00,
				0.00,
				4.00,
				0.00,
				0.00,
				array(
					new ProductReportSnapshot( 10, 'SKU-10', 'Reserva', 1.0, 50.00, 50.00, 4.00, 0.0, 0.00, 0.00, 12 ),
				)
			),
			new OrderReportSnapshot( 'cancelled', 'MO', 999.00, 0.00, 0.00, 0.00, 99.00, 0.00, 0.00, array() ),
		);

		$report  = SalesTaxReport::fromOrders( $orders );
		$summary = $report->summary();

		self::assertSame( 2, $summary['orders'] );
		self::assertSame( 150.00, $summary['gross_sales'] );
		self::assertSame( 10.00, $summary['discounts'] );
		self::assertSame( 20.00, $summary['refunds'] );
		self::assertSame( 120.00, $summary['net_sales'] );
		self::assertSame( 11.00, $summary['net_shipping'] );
		self::assertSame( 12.00, $summary['net_tax'] );
		self::assertSame( 143.00, $summary['net_collected'] );
		self::assertSame( 2.0, $summary['net_units'] );

		$states = $report->states();
		self::assertSame( 'IL', $states[0]['state'] );
		self::assertSame( 4.00, $states[0]['net_tax'] );
		self::assertSame( 'MO', $states[1]['state'] );
		self::assertSame( 8.00, $states[1]['net_tax'] );
		self::assertSame( 84.00, $states[1]['net_collected'] );

		$products = $report->products();
		self::assertCount( 1, $products );
		self::assertSame( 'SKU-10', $products[0]['sku'] );
		self::assertSame( 2.0, $products[0]['net_units'] );
		self::assertSame( 120.00, $products[0]['net_revenue'] );
		self::assertSame( 12.00, $products[0]['net_tax'] );
		self::assertSame( 12, $products[0]['stock_quantity'] );
	}

	public function test_reports_unknown_state_and_sorts_product_revenue_descending(): void {
		$orders = array(
			new OrderReportSnapshot(
				'refunded',
				'',
				30.00,
				0.00,
				0.00,
				0.00,
				3.00,
				3.00,
				30.00,
				array(
					new ProductReportSnapshot( 2, '', 'Lower', 1.0, 10.00, 10.00, 1.00, 0.0, 0.00, 0.00, null ),
					new ProductReportSnapshot( 1, 'HIGH', 'Higher', 2.0, 20.00, 20.00, 2.00, 2.0, 20.00, 2.00, 0 ),
				)
			),
		);

		$report = SalesTaxReport::fromOrders( $orders );

		self::assertSame( 'Unknown', $report->states()[0]['state'] );
		self::assertSame( 0.00, $report->states()[0]['net_tax'] );
		self::assertSame( 'Lower', $report->products()[0]['name'] );
		self::assertSame( 10.00, $report->products()[0]['net_revenue'] );
		self::assertSame( 'Higher', $report->products()[1]['name'] );
		self::assertSame( 0.00, $report->products()[1]['net_revenue'] );
	}

	public function test_product_filter_reports_only_selected_product_and_does_not_allocate_shipping(): void {
		$order = new OrderReportSnapshot(
			'completed',
			'MO',
			100.00,
			10.00,
			8.00,
			0.00,
			9.00,
			0.00,
			0.00,
			array(
				new ProductReportSnapshot( 10, 'TEN', 'Ten', 1.0, 60.00, 54.00, 5.40, 0.0, 0.00, 0.00, 5 ),
				new ProductReportSnapshot( 20, 'TWENTY', 'Twenty', 1.0, 40.00, 36.00, 3.60, 0.0, 0.00, 0.00, 5 ),
			)
		);

		$summary = SalesTaxReport::fromOrders( array( $order ), 10 )->summary();

		self::assertSame( 1, $summary['orders'] );
		self::assertSame( 60.00, $summary['gross_sales'] );
		self::assertSame( 6.00, $summary['discounts'] );
		self::assertSame( 54.00, $summary['net_sales'] );
		self::assertSame( 0.00, $summary['net_shipping'] );
		self::assertSame( 5.40, $summary['net_tax'] );
		self::assertSame( 59.40, $summary['net_collected'] );
	}

	public function test_unallocated_refunds_reduce_collected_amount_without_rewriting_product_sales(): void {
		$order = new OrderReportSnapshot(
			'completed',
			'MO',
			100.00,
			0.00,
			0.00,
			0.00,
			8.44,
			0.00,
			0.00,
			array(),
			false,
			10.00
		);

		$summary = SalesTaxReport::fromOrders( array( $order ) )->summary();

		self::assertSame( 0.00, $summary['refunds'] );
		self::assertSame( 10.00, $summary['unallocated_refunds'] );
		self::assertSame( 100.00, $summary['net_sales'] );
		self::assertSame( 98.44, $summary['net_collected'] );
	}
}
