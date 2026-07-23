<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Shipping;

use RuntimeException;

/**
 * Normalized shipping-provider failure. Callers fail closed on this exception.
 */
final class ShippingProviderException extends RuntimeException {}
