<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class RestrictionRuleInput {

	/**
	 * @param array<string, int|string|null> $rule
	 * @param array<string, list<string>>     $targets
	 */
	private function __construct( private array $rule, private array $targets ) {
	}

	/** @param array<string, mixed> $values */
	public static function fromArray( array $values, DateTimeZone $timezone ): self {
		$name            = self::text( $values['name'] ?? '', 191 );
		$country         = strtoupper( self::text( $values['country'] ?? '', 2 ) );
		$blocked_message = self::text( $values['blocked_message'] ?? '', 500 );
		$effective       = self::dateTime( $values['effective_at'] ?? '', $timezone, false );
		$expires         = self::dateTime( $values['expires_at'] ?? '', $timezone, true );
		if ( '' === $name || 'US' !== $country || '' === $blocked_message || ( null !== $expires && $expires <= $effective ) ) {
			throw new DomainException( 'Restriction rule fields are invalid.' );
		}
		$targets = array(
			'state'         => self::values( $values['state'] ?? '', static fn ( string $value ): string => strtoupper( $value ), '/^[A-Z]{2}$/' ),
			'city'          => self::values( $values['city'] ?? '', static fn ( string $value ): string => mb_strtolower( $value ), '/^[\p{L}\p{N} .\'-]{1,100}$/u' ),
			'postal_code'   => self::values( $values['postal_code'] ?? '', self::postal( ... ), '/^[A-Z0-9-]{3,10}$/' ),
			'postal_prefix' => self::values( $values['postal_prefix'] ?? '', self::postal( ... ), '/^[A-Z0-9-]{1,9}$/' ),
			'product_id'    => self::ids( $values['product_id'] ?? '' ),
			'category_id'   => self::ids( $values['category_id'] ?? '' ),
			'brand_id'      => self::ids( $values['brand_id'] ?? '' ),
		);
		if ( array() === array_filter( $targets ) ) {
			throw new DomainException( 'At least one destination or cart scope is required.' );
		}
		$source_url = self::sourceUrl( $values['source_url'] ?? '' );
		$review     = self::date( $values['review_date'] ?? '' );
		return new self(
			array(
				'name'            => $name,
				'enabled'         => ! empty( $values['enabled'] ) ? 1 : 0,
				'priority'        => max( -10000, min( 10000, (int) ( $values['priority'] ?? 0 ) ) ),
				'country'         => $country,
				'effective_at'    => $effective->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				'expires_at'      => $expires?->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				'blocked_message' => $blocked_message,
				'notes'           => self::text( $values['notes'] ?? '', 2000 ),
				'source_name'     => self::text( $values['source_name'] ?? '', 191 ),
				'source_url'      => $source_url,
				'review_date'     => $review,
			),
			$targets
		);
	}

	/** @return array<string, int|string|null> */
	public function rule(): array {
		return $this->rule;
	}

	/** @return array<string, list<string>> */
	public function targets(): array {
		return $this->targets;
	}

	private static function text( mixed $value, int $limit ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Domain input remains unit-testable without WordPress runtime functions.
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', strip_tags( (string) $value ) ) ?? '';
		return mb_substr( trim( $value ), 0, $limit );
	}

	private static function postal( string $value ): string {
		return strtoupper( preg_replace( '/\s+/', '', $value ) ?? '' );
	}

	/**
	 * @param callable(string):string $normalize
	 * @return list<string>
	 */
	private static function values( mixed $values, callable $normalize, string $pattern ): array {
		$parts  = is_array( $values ) ? $values : explode( ',', (string) $values );
		$result = array();
		foreach ( $parts as $part ) {
			$value = $normalize( trim( (string) $part ) );
			if ( '' === $value ) {
				continue;
			}
			if ( 1 !== preg_match( $pattern, $value ) ) {
				throw new DomainException( 'Restriction target value is invalid.' );
			}
			$result[] = $value;
		}
		$result = array_values( array_unique( $result ) );
		sort( $result );
		return $result;
	}

	/** @return list<string> */
	private static function ids( mixed $values ): array {
		$parts  = is_array( $values ) ? $values : explode( ',', (string) $values );
		$result = array();
		foreach ( $parts as $part ) {
			if ( '' === trim( (string) $part ) ) {
				continue;
			}
			$id = filter_var( trim( (string) $part ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false === $id ) {
				throw new DomainException( 'Restriction target ID is invalid.' );
			}
			$result[] = (string) $id;
		}
		$result = array_values( array_unique( $result ) );
		sort( $result, SORT_NUMERIC );
		return $result;
	}

	private static function dateTime( mixed $value, DateTimeZone $timezone, bool $optional ): ?DateTimeImmutable {
		$value = trim( (string) $value );
		if ( $optional && '' === $value ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, $timezone );
		if ( false === $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			throw new DomainException( 'Restriction date and time is invalid.' );
		}
		return $date;
	}

	private static function date( mixed $value ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw new DomainException( 'Review date is invalid.' );
		}
		return $value;
	}

	private static function sourceUrl( mixed $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Domain input remains unit-testable without WordPress runtime functions.
		$parts = parse_url( $value );
		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) || ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			throw new DomainException( 'Source URL must be a public HTTPS URL without credentials, query parameters, or fragments.' );
		}
		return mb_substr( $value, 0, 2048 );
	}
}
