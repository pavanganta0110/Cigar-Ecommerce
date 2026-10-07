<?php

declare(strict_types=1);

namespace Compadres\Commerce\Fulfillment;

use DateTimeImmutable;

/** The few order facts the fulfillment board needs; carries no addresses. */
final class FulfillmentRecord {

	public function __construct(
		private int $order_id,
		private string $order_number,
		private DateTimeImmutable $created,
		private string $status,
		private string $customer_name,
		private int $item_count,
		private float $total,
		private string $tracking_number,
		private string $carrier_status_text,
		private bool $delivered
	) {
	}

	public function orderId(): int {
		return $this->order_id;
	}

	public function orderNumber(): string {
		return $this->order_number;
	}

	public function created(): DateTimeImmutable {
		return $this->created;
	}

	public function status(): string {
		return $this->status;
	}

	public function customerName(): string {
		return $this->customer_name;
	}

	public function itemCount(): int {
		return $this->item_count;
	}

	public function total(): float {
		return $this->total;
	}

	public function trackingNumber(): string {
		return $this->tracking_number;
	}

	public function carrierStatusText(): string {
		return $this->carrier_status_text;
	}

	public function delivered(): bool {
		return $this->delivered;
	}

	public function stage(): string {
		return FulfillmentStage::classify( $this->status, '' !== $this->tracking_number, $this->delivered );
	}
}
