<?php

declare( strict_types = 1 );

namespace Compadres\Commerce\Reporting;

use DateTimeImmutable;

/** Validated read-only report filters and date presets. */
final class ReportFilters {

	private function __construct(
		private string $preset,
		private DateTimeImmutable $from,
		private DateTimeImmutable $to,
		private string $state,
		private int $product_id
	) {
	}

	/** @param array<string, mixed> $input */
	public static function fromInput( array $input, DateTimeImmutable $now ): self {
		$preset = isset( $input['period'] ) && is_string( $input['period'] )
			? strtolower( trim( $input['period'] ) )
			: 'month';
		$valid  = array( 'today', 'week', 'month', 'quarter', 'year', 'custom' );
		if ( ! in_array( $preset, $valid, true ) ) {
			$preset = 'month';
		}
		$range = self::presetRange( $preset, $now, $input );
		if ( null === $range ) {
			$preset = 'month';
			$range  = self::presetRange( $preset, $now, array() );
		}
		$state = isset( $input['state'] ) && is_string( $input['state'] )
			? strtoupper( trim( $input['state'] ) )
			: '';
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $state ) ) {
			$state = '';
		}
		$product_id = isset( $input['product_id'] ) && is_numeric( $input['product_id'] )
			? max( 0, (int) $input['product_id'] )
			: 0;
		return new self( $preset, $range[0], $range[1], $state, $product_id );
	}

	public function preset(): string {
		return $this->preset; }
	public function from(): DateTimeImmutable {
		return $this->from; }
	public function to(): DateTimeImmutable {
		return $this->to; }
	public function state(): string {
		return $this->state; }
	public function productId(): int {
		return $this->product_id; }

	/**
	 * @param array<string, mixed> $input
	 * @return array{DateTimeImmutable, DateTimeImmutable}|null
	 */
	private static function presetRange( string $preset, DateTimeImmutable $now, array $input ): ?array {
		$end = $now->setTime( 23, 59, 59 );
		switch ( $preset ) {
			case 'today':
				$start = $now->setTime( 0, 0, 0 );
				break;
			case 'week':
				$start = $now->modify( 'monday this week' )->setTime( 0, 0, 0 );
				break;
			case 'quarter':
				$month = ( (int) floor( ( (int) $now->format( 'n' ) - 1 ) / 3 ) * 3 ) + 1;
				$start = $now->setDate( (int) $now->format( 'Y' ), $month, 1 )->setTime( 0, 0, 0 );
				break;
			case 'year':
				$start = $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0, 0 );
				break;
			case 'custom':
				$start = self::parseDate( $input['date_from'] ?? null, $now, false );
				$end   = self::parseDate( $input['date_to'] ?? null, $now, true );
				if ( null === $start || null === $end || $start > $end ) {
					return null;
				}
				break;
			case 'month':
			default:
				$start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				break;
		}
		return array( $start, $end );
	}

	private static function parseDate( mixed $value, DateTimeImmutable $now, bool $end ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $now->getTimezone() );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}
		return $end ? $date->setTime( 23, 59, 59 ) : $date->setTime( 0, 0, 0 );
	}
}
