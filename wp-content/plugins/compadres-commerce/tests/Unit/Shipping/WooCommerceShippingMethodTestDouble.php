<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Tests\Unit\Shipping;

/** Minimal test double loaded before the production subclass is autoloaded. */
class WooCommerceShippingMethodTestDouble {
	public string $id                 = '';
	public int $instance_id           = 0;
	public string $method_title       = '';
	public string $method_description = '';
	/** @var list<string> */
	public array $supports = array();
	public string $enabled = 'yes';
	public string $title   = '';
	/** @var array<string, mixed> */
	public array $instance_form_fields = array();
	/** @var array<string, array<string, mixed>> */
	public array $rates = array();

	public function __construct( int $instance_id = 0 ) {
		$this->instance_id = $instance_id;
	}

	/** @param array<string, mixed> $rate */
	public function add_rate( array $rate ): void {
		$this->rates[ (string) $rate['id'] ] = $rate;
	}

	public function get_rate_id( mixed $suffix = '' ): string {
		return $this->id . ':' . $this->instance_id . ':' . (string) $suffix;
	}
}
