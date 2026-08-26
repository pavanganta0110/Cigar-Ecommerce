<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

/** Immutable product-line values used by sales reporting. */
final class ProductReportSnapshot {

	public function __construct(
		private int $product_id,
		private string $sku,
		private string $name,
		private float $quantity,
		private float $gross_revenue,
		private float $discounted_revenue,
		private float $tax,
		private float $refunded_quantity,
		private float $refunded_revenue,
		private float $refunded_tax,
		private ?int $stock_quantity
	) {
	}

	public function productId(): int {
		return $this->product_id; }
	public function sku(): string {
		return $this->sku; }
	public function name(): string {
		return $this->name; }
	public function quantity(): float {
		return $this->quantity; }
	public function grossRevenue(): float {
		return $this->gross_revenue; }
	public function discountedRevenue(): float {
		return $this->discounted_revenue; }
	public function tax(): float {
		return $this->tax; }
	public function refundedQuantity(): float {
		return $this->refunded_quantity; }
	public function refundedRevenue(): float {
		return $this->refunded_revenue; }
	public function refundedTax(): float {
		return $this->refunded_tax; }
	public function stockQuantity(): ?int {
		return $this->stock_quantity; }
}
