<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

/**
 * Minimal WooCommerce order metadata for shipping eligibility.
 *
 * Only the fields required for fulfillment and support are stored. No carrier
 * payload, rate quote, or customer address is persisted here.
 */
final class OrderShippingMeta {

	public const ADULT_SIGNATURE_REQUIRED = '_compadres_shipping_adult_signature_required';
	public const PROVIDER                 = '_compadres_shipping_provider';
	public const SERVICE                  = '_compadres_shipping_service';
	public const SERVICE_REFERENCE        = '_compadres_shipping_service_reference';
	public const ELIGIBILITY              = '_compadres_shipping_eligibility';
	public const ELIGIBILITY_CHECKED_AT   = '_compadres_shipping_eligibility_checked_at';

	public const ELIGIBILITY_ALLOWED = 'allowed';
	public const ELIGIBILITY_BLOCKED = 'blocked';
}
