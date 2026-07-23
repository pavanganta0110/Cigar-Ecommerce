<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

final class BrandData {

	/**
	 * @param  array<string, mixed> $input Raw values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( static fn ( mixed $id ): int => max( 0, (int) $id ), (array) ( $input['featured_product_ids'] ?? array() ) )
				)
			)
		);

		return array(
			'short_description'    => self::text( $input['short_description'] ?? '' ),
			'logo_attachment_id'   => max( 0, (int) ( $input['logo_attachment_id'] ?? 0 ) ),
			'hero_attachment_id'   => max( 0, (int) ( $input['hero_attachment_id'] ?? 0 ) ),
			'accent_color'         => self::color( $input['accent_color'] ?? '' ),
			'brand_story'          => self::text( $input['brand_story'] ?? '' ),
			'seo_title'            => self::text( $input['seo_title'] ?? '' ),
			'meta_description'     => self::text( $input['meta_description'] ?? '' ),
			'featured_product_ids' => $ids,
			'display_order'        => max( 0, (int) ( $input['display_order'] ?? 0 ) ),
			'active'               => in_array( $input['active'] ?? false, array( true, 1, '1', 'yes', 'on' ), true ),
			'future_odoo_id'       => self::identifier( $input['future_odoo_id'] ?? '' ),
			'future_wholesale_id'  => self::identifier( $input['future_wholesale_id'] ?? '' ),
		);
	}

	private static function text( mixed $value ): string {
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}

	private static function identifier( mixed $value ): string {
		return preg_replace( '/[^A-Za-z0-9_.:-]/', '', trim( (string) $value ) ) ?? '';
	}

	private static function color( mixed $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}
}
