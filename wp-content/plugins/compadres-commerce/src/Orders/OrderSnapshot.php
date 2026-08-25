<?php

declare(strict_types=1);

namespace Compadres\Commerce\Orders;

use JsonException;

/**
 * The canonical, versioned, point-in-time record of an order's commerce and
 * compliance state, immune to later product, brand, or rule-set edits.
 */
final class OrderSnapshot {

	/**
	 * @param list<array<string, mixed>> $lines
	 * @param array<string, mixed>       $compliance
	 */
	public function __construct(
		private int $schema_version,
		private int $order_id,
		private string $created_at,
		private string $customer_type,
		private string $order_status,
		private string $currency,
		private float $subtotal,
		private float $discount_total,
		private float $shipping_total,
		private float $tax_total,
		private float $total,
		private array $lines,
		private array $compliance
	) {}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'schema_version' => $this->schema_version,
			'order_id'       => $this->order_id,
			'created_at'     => $this->created_at,
			'customer_type'  => $this->customer_type,
			'order_status'   => $this->order_status,
			'currency'       => $this->currency,
			'subtotal'       => $this->subtotal,
			'discount_total' => $this->discount_total,
			'shipping_total' => $this->shipping_total,
			'tax_total'      => $this->tax_total,
			'total'          => $this->total,
			'lines'          => $this->lines,
			'compliance'     => $this->compliance,
		);
	}

	public function toJson(): string {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure, WordPress-independent value object; must stay unit-testable without the WP runtime.
			return json_encode( $this->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION );
		} catch ( JsonException ) {
			return '';
		}
	}
}
