<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use WP_Query;

final class CatalogFilters {

	private const TEXT_FILTERS = array( 'strength', 'wrapper', 'country_of_origin', 'vitola' );

	public function registerHooks(): void {
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render' ), 15 );
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
		add_filter( 'posts_search', array( $this, 'includeSkuInSearch' ), 10, 2 );
	}

	public function render(): void {
		if ( ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}
		echo '<details class="catalog-filters" open><summary>' . esc_html__( 'Filter cigars', 'compadres-commerce' ) . '</summary><form method="get">';
		$this->select( 'brand', __( 'Brand', 'compadres-commerce' ), $this->brandOptions() );
		foreach ( self::TEXT_FILTERS as $filter ) {
			$this->select( $filter, ucwords( str_replace( '_', ' ', $filter ) ), $this->metaOptions( $filter ) );
		}
		$this->select(
			'pack_type',
			__( 'Pack type', 'compadres-commerce' ),
			array(
				'single'  => 'Single',
				'pack'    => 'Pack',
				'box'     => 'Box',
				'sampler' => 'Sampler',
			)
		);
		$this->select(
			'availability',
			__( 'Availability', 'compadres-commerce' ),
			array(
				'instock'    => 'In stock',
				'outofstock' => 'Out of stock',
			)
		);
		echo '<label>' . esc_html__( 'Minimum price', 'compadres-commerce' ) . '<input type="number" min="0" step="0.01" name="compadres_min_price" value="' . esc_attr( $this->queryValue( 'compadres_min_price' ) ) . '"></label>';
		echo '<label>' . esc_html__( 'Maximum price', 'compadres-commerce' ) . '<input type="number" min="0" step="0.01" name="compadres_max_price" value="' . esc_attr( $this->queryValue( 'compadres_max_price' ) ) . '"></label>';
		echo '<button type="submit">' . esc_html__( 'Apply filters', 'compadres-commerce' ) . '</button> <a href="' . esc_url( $this->clearUrl() ) . '">' . esc_html__( 'Clear all', 'compadres-commerce' ) . '</a></form></details>';
	}

	/** @param array<string, string> $options */
	private function select( string $name, string $label, array $options ): void {
		$current = $this->queryValue( $name );
		echo '<label>' . esc_html( $label ) . '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Any', 'compadres-commerce' ) . '</option>';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	/** @return array<string, string> */
	private function brandOptions(): array {
		$terms   = get_terms(
			array(
				'taxonomy'   => BrandTaxonomy::TAXONOMY,
				'hide_empty' => true,
			)
		);
		$options = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->slug ] = $term->name; }
		}
		return $options;
	}

	/** @return array<string, string> */
	private function metaOptions( string $field ): array {
		global $wpdb;
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value LIMIT 100", ProductMetadata::PREFIX . $field ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		/** @var array<string, string> $options */
		$options = array_combine( array_map( 'sanitize_title', $values ), $values );
		return $options;
	}

	public function apply( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
			return;
		}
		$tax_query = (array) $query->get( 'tax_query' );
		$brand     = $this->queryValue( 'brand' );
		if ( $brand ) {
			$tax_query[] = array(
				'taxonomy' => BrandTaxonomy::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( sanitize_title( $brand ) ),
			);
		}
		$query->set( 'tax_query', $tax_query );
		$meta_query = (array) $query->get( 'meta_query' );
		foreach ( self::TEXT_FILTERS as $field ) {
			$value = $this->queryValue( $field );
			if ( $value ) {
				$meta_query[] = array(
					'key'     => ProductMetadata::PREFIX . $field,
					'value'   => sanitize_text_field( $value ),
					'compare' => '=',
				);
			}
		}
		$availability = $this->queryValue( 'availability' );
		if ( in_array( $availability, array( 'instock', 'outofstock' ), true ) ) {
			$meta_query[] = array(
				'key'   => '_stock_status',
				'value' => $availability,
			);
		}
		$min = max( 0.0, (float) $this->queryValue( 'compadres_min_price' ) );
		$max = max( 0.0, (float) $this->queryValue( 'compadres_max_price' ) );
		if ( $min || $max ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => array( $min, $max > 0 ? $max : PHP_FLOAT_MAX ),
				'type'    => 'DECIMAL',
				'compare' => 'BETWEEN',
			);
		}
		$query->set( 'meta_query', $meta_query );
	}

	public function includeSkuInSearch( string $search, WP_Query $query ): string {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() || '' === $query->get( 's' ) ) {
			return $search;
		}
		global $wpdb;
		$term    = (string) $query->get( 's' );
		$sku_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $term ) . '%'
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $sku_ids ) {
			return $search;
		}
		$normal_search = preg_replace( '/^\\s*AND\\s*/', '', $search ) ?? $search;
		$ids           = implode( ',', array_map( 'absint', $sku_ids ) );
		return " AND (({$normal_search}) OR {$wpdb->posts}.ID IN ({$ids}))"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function queryValue( string $key ): string {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private function clearUrl(): string {
		$url = strtok( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/shop/' ) ), '?' );
		return false !== $url ? $url : wc_get_page_permalink( 'shop' );
	}
}
