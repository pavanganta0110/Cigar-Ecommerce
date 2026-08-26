<?php

declare(strict_types=1);

namespace Compadres\Commerce\Orders;

/** Catalog values captured at order time so later product/brand edits cannot change history. */
final class OrderLineSnapshot {

	/**
	 * @param list<string> $brand_names
	 * @param list<string> $brand_slugs
	 */
	public function __construct(
		private int $product_id,
		private int $variation_id,
		private string $sku,
		private string $name,
		private array $brand_names,
		private array $brand_slugs,
		private float $quantity,
		private float $subtotal,
		private float $total
	) {}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'product_id'   => $this->product_id,
			'variation_id' => $this->variation_id,
			'sku'          => $this->sku,
			'name'         => $this->name,
			'brand_names'  => $this->brand_names,
			'brand_slugs'  => $this->brand_slugs,
			'quantity'     => $this->quantity,
			'subtotal'     => $this->subtotal,
			'total'        => $this->total,
		);
	}
}
