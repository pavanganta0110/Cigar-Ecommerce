<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

/** Generates accountant-friendly CSV without spreadsheet-formula injection. */
final class SalesTaxCsv {

	/**
	 * @param list<array<string, mixed>> $states
	 * @param list<array<string, mixed>> $products
	 */
	public static function render( array $states, array $products ): string {
		$stream = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV stream; no filesystem path.
		if ( false === $stream ) {
			return '';
		}
		self::row( $stream, array( 'State summary' ) );
		self::row( $stream, array( 'State', 'Orders', 'Gross sales', 'Discounts', 'Product refunds', 'Unallocated refunds', 'Net sales', 'Net shipping', 'Net tax collected', 'Net collected' ) );
		foreach ( $states as $state ) {
			self::row( $stream, array( $state['state'], $state['orders'], $state['gross_sales'], $state['discounts'], $state['refunds'], $state['unallocated_refunds'], $state['net_sales'], $state['net_shipping'], $state['net_tax'], $state['net_collected'] ) );
		}
		self::row( $stream, array() );
		self::row( $stream, array( 'Product summary' ) );
		self::row( $stream, array( 'Product ID', 'SKU', 'Product', 'Units', 'Refunded units', 'Net units', 'Gross revenue', 'Discounts', 'Refunds', 'Net revenue', 'Net tax collected', 'Current stock' ) );
		foreach ( $products as $product ) {
			self::row( $stream, array( $product['product_id'], $product['sku'], $product['name'], $product['units'], $product['refunded_units'], $product['net_units'], $product['gross_revenue'], $product['discounts'], $product['refunds'], $product['net_revenue'], $product['net_tax'], $product['stock_quantity'] ) );
		}
		rewind( $stream );
		$content = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the in-memory stream.
		return false !== $content ? $content : '';
	}

	/**
	 * @param resource    $stream In-memory CSV stream.
	 * @param list<mixed> $values CSV cell values.
	 */
	private static function row( $stream, array $values ): void {
		$values = array_map( self::safeCell( ... ), $values );
		fputcsv( $stream, $values, ',', '"', '\\' );
	}

	private static function safeCell( mixed $value ): string|int|float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		$text         = (string) $value;
		$is_dangerous = 1 === preg_match( '/^(?:[\x00-\x20]*[=+\-@]|[\x00-\x20]*[	\r\n])/', $text );
		return $is_dangerous ? "'" . $text : $text;
	}
}
