<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

/** Immutable order values used by sales and state-tax reporting. */
final class OrderReportSnapshot {

	/** @param list<ProductReportSnapshot> $products */
	public function __construct(
		private string $status,
		private string $state,
		private float $gross_sales,
		private float $discounts,
		private float $shipping,
		private float $refunded_shipping,
		private float $tax,
		private float $refunded_tax,
		private float $refunded_sales,
		private array $products,
		private bool $counts_as_order = true,
		private float $unallocated_refunds = 0.0
	) {
	}

	public function status(): string {
		return $this->status; }
	public function state(): string {
		return $this->state; }
	public function grossSales(): float {
		return $this->gross_sales; }
	public function discounts(): float {
		return $this->discounts; }
	public function shipping(): float {
		return $this->shipping; }
	public function refundedShipping(): float {
		return $this->refunded_shipping; }
	public function tax(): float {
		return $this->tax; }
	public function refundedTax(): float {
		return $this->refunded_tax; }
	public function refundedSales(): float {
		return $this->refunded_sales; }
	public function unallocatedRefunds(): float {
		return $this->unallocated_refunds; }
	public function countsAsOrder(): bool {
		return $this->counts_as_order; }

	/** @return list<ProductReportSnapshot> */
	public function products(): array {
		return $this->products; }
}
