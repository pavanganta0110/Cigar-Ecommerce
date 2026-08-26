<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

/** Aggregates finalized WooCommerce order snapshots without customer data. */
final class SalesTaxReport {

	private const INCLUDED_STATUSES = array( 'processing', 'completed', 'refunded' );

	/**
	 * @param array<string, int|float>             $summary  Overall totals.
	 * @param list<array<string, int|float|string>> $states   State totals.
	 * @param list<array<string, int|float|string|null>> $products Product totals.
	 */
	private function __construct(
		private array $summary,
		private array $states,
		private array $products
	) {
	}

	/** @param list<OrderReportSnapshot> $orders */
	public static function fromOrders( array $orders, int $product_id = 0 ): self {
		$summary  = self::emptySummary();
		$states   = array();
		$products = array();
		foreach ( $orders as $order ) {
			if ( ! in_array( $order->status(), self::INCLUDED_STATUSES, true ) ) {
				continue;
			}
			if ( $product_id > 0 ) {
				$order = self::forProduct( $order, $product_id );
				if ( null === $order ) {
					continue;
				}
			}
			$values = self::orderValues( $order );
			self::addValues( $summary, $values );
			if ( $order->countsAsOrder() ) {
				++$summary['orders'];
			}

			$state = strtoupper( trim( $order->state() ) );
			$state = '' !== $state ? $state : 'Unknown';
			if ( ! isset( $states[ $state ] ) ) {
				$states[ $state ] = array( 'state' => $state ) + self::emptySummary();
			}
			self::addValues( $states[ $state ], $values );
			if ( $order->countsAsOrder() ) {
				++$states[ $state ]['orders'];
			}

			foreach ( $order->products() as $line ) {
				$key = (string) $line->productId();
				if ( ! isset( $products[ $key ] ) ) {
					$products[ $key ] = array(
						'product_id'     => $line->productId(),
						'sku'            => $line->sku(),
						'name'           => $line->name(),
						'units'          => 0.0,
						'refunded_units' => 0.0,
						'net_units'      => 0.0,
						'gross_revenue'  => 0.0,
						'discounts'      => 0.0,
						'refunds'        => 0.0,
						'net_revenue'    => 0.0,
						'net_tax'        => 0.0,
						'stock_quantity' => $line->stockQuantity(),
					);
				}
				$discount                            = max( 0.0, $line->grossRevenue() - $line->discountedRevenue() );
				$products[ $key ]['units']          += $line->quantity();
				$products[ $key ]['refunded_units'] += $line->refundedQuantity();
				$products[ $key ]['net_units']      += $line->quantity() - $line->refundedQuantity();
				$products[ $key ]['gross_revenue']  += $line->grossRevenue();
				$products[ $key ]['discounts']      += $discount;
				$products[ $key ]['refunds']        += $line->refundedRevenue();
				$products[ $key ]['net_revenue']    += $line->discountedRevenue() - $line->refundedRevenue();
				$products[ $key ]['net_tax']        += $line->tax() - $line->refundedTax();
				$summary['net_units']               += $line->quantity() - $line->refundedQuantity();
			}
		}
		ksort( $states );
		usort(
			$products,
			static fn ( array $left, array $right ): int => $right['net_revenue'] <=> $left['net_revenue']
		);
		return new self( self::roundValues( $summary ), array_map( self::roundValues( ... ), array_values( $states ) ), array_map( self::roundValues( ... ), $products ) );
	}

	/** @return array<string, int|float> */
	public function summary(): array {
		return $this->summary; }

	/** @return list<array<string, int|float|string>> */
	public function states(): array {
		return $this->states; }

	/** @return list<array<string, int|float|string|null>> */
	public function products(): array {
		return $this->products; }

	/** @return array<string, int|float> */
	private static function emptySummary(): array {
		return array(
			'orders'              => 0,
			'gross_sales'         => 0.0,
			'discounts'           => 0.0,
			'refunds'             => 0.0,
			'unallocated_refunds' => 0.0,
			'net_sales'           => 0.0,
			'net_shipping'        => 0.0,
			'net_tax'             => 0.0,
			'net_collected'       => 0.0,
			'net_units'           => 0.0,
		);
	}

	/** @return array<string, float> */
	private static function orderValues( OrderReportSnapshot $order ): array {
		$net_sales    = $order->grossSales() - $order->discounts() - $order->refundedSales();
		$net_shipping = $order->shipping() - $order->refundedShipping();
		$net_tax      = $order->tax() - $order->refundedTax();
		return array(
			'gross_sales'         => $order->grossSales(),
			'discounts'           => $order->discounts(),
			'refunds'             => $order->refundedSales(),
			'unallocated_refunds' => $order->unallocatedRefunds(),
			'net_sales'           => $net_sales,
			'net_shipping'        => $net_shipping,
			'net_tax'             => $net_tax,
			'net_collected'       => $net_sales + $net_shipping + $net_tax - $order->unallocatedRefunds(),
		);
	}

	private static function forProduct( OrderReportSnapshot $order, int $product_id ): ?OrderReportSnapshot {
		$lines = array_values(
			array_filter(
				$order->products(),
				static fn ( ProductReportSnapshot $line ): bool => $line->productId() === $product_id
			)
		);
		if ( array() === $lines ) {
			return null;
		}
		$gross_sales    = 0.0;
		$discounted     = 0.0;
		$tax            = 0.0;
		$refunded_sales = 0.0;
		$refunded_tax   = 0.0;
		foreach ( $lines as $line ) {
			$gross_sales    += $line->grossRevenue();
			$discounted     += $line->discountedRevenue();
			$tax            += $line->tax();
			$refunded_sales += $line->refundedRevenue();
			$refunded_tax   += $line->refundedTax();
		}
		return new OrderReportSnapshot(
			$order->status(),
			$order->state(),
			$gross_sales,
			max( 0.0, $gross_sales - $discounted ),
			0.0,
			0.0,
			$tax,
			$refunded_tax,
			$refunded_sales,
			$lines,
			$order->countsAsOrder()
		);
	}

	/**
	 * @param array<string, mixed> $target Aggregate target.
	 * @param array<string, float> $values Numeric values to add.
	 */
	private static function addValues( array &$target, array $values ): void {
		foreach ( $values as $key => $value ) {
			if ( ! isset( $target[ $key ] ) || ! is_numeric( $target[ $key ] ) ) {
				continue;
			}
			$target[ $key ] = (float) $target[ $key ] + $value;
		}
	}

	/**
	 * @param array<string, mixed> $values Values to normalize.
	 * @return array<string, mixed>
	 */
	private static function roundValues( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( is_float( $value ) ) {
				$values[ $key ] = round( $value, 2 );
			}
		}
		return $values;
	}
}
