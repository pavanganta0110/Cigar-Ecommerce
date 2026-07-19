<?php

declare(strict_types=1);

namespace Compadres\Commerce\AgeVerification;

use WC_Order;

final class WooCommerceOrderMetaWriter implements OrderMetaStore {

	public function __construct( private WC_Order $order ) {}

	public function set( string $key, mixed $value ): void {
		$this->order->update_meta_data( $key, $value );
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		$value = $this->order->get_meta( $key, true );
		return '' === $value ? $fallback : $value;
	}
}
