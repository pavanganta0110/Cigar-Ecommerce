<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_CLI;

final class FixtureCommand {

	private const PREFIX = 'DEV-FICTIONAL-';

	public function load(): void {
		if ( ! $this->allowed() ) {
			WP_CLI::error( 'Fixtures are disabled outside local/development or explicitly enabled staging environments.' );
		}
		$brands = $this->loadBrands();
		$this->ensureCategory( 'samplers', 'Fictional Development Samplers' );
		$this->ensureCategory( 'new-releases', 'Fictional Development New Releases' );
		$products = array(
			array( 'Ember Quay Fictional Robusto Single', 'ROBUSTO-SINGLE', '8.50', 32, 'medium', 'Connecticut', 'Robusto', 1, 0, $brands['ember-quay'], true, 18 ),
			array( 'Ember Quay Fictional Robusto Five Pack', 'ROBUSTO-PACK', '39.00', 12, 'medium', 'Connecticut', 'Robusto', 5, 0, $brands['ember-quay'], true, 14 ),
			array( 'Ember Quay Fictional Robusto Box', 'ROBUSTO-BOX', '145.00', 4, 'medium', 'Connecticut', 'Robusto', 0, 20, $brands['ember-quay'], false, 7 ),
			array( 'Lantern House Fictional Toro Single', 'TORO-SINGLE', '10.00', 28, 'medium-full', 'Habano', 'Toro', 1, 0, $brands['lantern-house'], true, 22 ),
			array( 'Lantern House Fictional Toro Box', 'TORO-BOX', '178.00', 5, 'medium-full', 'Habano', 'Toro', 0, 20, $brands['lantern-house'], false, 9 ),
			array( 'Meridian Workshop Fictional Churchill Single', 'CHURCHILL-SINGLE', '11.25', 19, 'full', 'Maduro', 'Churchill', 1, 0, $brands['meridian-workshop'], false, 11 ),
			array( 'Meridian Workshop Fictional Churchill Pack', 'CHURCHILL-PACK', '52.00', 8, 'full', 'Maduro', 'Churchill', 5, 0, $brands['meridian-workshop'], false, 6 ),
			array( 'Compadres Fictional Discovery Sampler', 'SAMPLER', '48.00', 15, 'medium', 'Assorted', 'Sampler', 6, 0, $brands['meridian-workshop'], true, 16 ),
		);
		foreach ( $products as $data ) {
			$this->ensureSimpleProduct( $data );
		}
		$this->ensureVariableProduct( $brands['lantern-house'] );
		update_option( 'compadres_fixture_version', '1', false );
		WP_CLI::success( 'Fictional development brands and products are loaded idempotently.' );
	}

	public function remove(): void {
		if ( ! $this->allowed() ) {
			WP_CLI::error( 'Fixture removal is disabled in this environment.' );
		}
		$products = wc_get_products(
			array(
				'limit'      => -1,
				'status'     => array_keys( get_post_stati() ),
				'meta_key'   => '_compadres_fixture',
				'meta_value' => '1',
				'return'     => 'objects',
			)
		);
		foreach ( $products as $product ) {
			$product->delete( true );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => BrandTaxonomy::TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => 'compadres_fixture',
				'meta_value' => '1',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, BrandTaxonomy::TAXONOMY ); }
		}
		delete_option( 'compadres_fixture_version' );
		WP_CLI::success( 'Only Compadres fictional development fixtures were removed. Orders and non-fixture products were preserved.' );
	}

	/** @return array<string, int> */
	private function loadBrands(): array {
		$definitions = array(
			'ember-quay'        => array( 'Ember Quay — Fictional Development Brand', '#9b5a32', 'A fictional brand used only to test light and medium-strength catalog paths.' ),
			'lantern-house'     => array( 'Lantern House — Fictional Development Brand', '#b88a44', 'A fictional brand used only to test packs, boxes, and variations.' ),
			'meridian-workshop' => array( 'Meridian Workshop — Fictional Development Brand', '#6f4938', 'A fictional brand used only to test full-strength and sampler catalog paths.' ),
		);
		$ids         = array();
		$order       = 1;
		foreach ( $definitions as $slug => $definition ) {
			$term = term_exists( $slug, BrandTaxonomy::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term(
					$definition[0],
					BrandTaxonomy::TAXONOMY,
					array(
						'slug'        => $slug,
						'description' => $definition[2],
					)
				);
			}
			if ( is_wp_error( $term ) ) {
				WP_CLI::error( $term->get_error_message() ); }
			$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			update_term_meta( $term_id, 'compadres_accent_color', $definition[1] );
			update_term_meta( $term_id, 'compadres_short_description', $definition[2] );
			update_term_meta( $term_id, 'compadres_display_order', $order++ );
			update_term_meta( $term_id, 'compadres_active', true );
			update_term_meta( $term_id, 'compadres_fixture', true );
			$ids[ $slug ] = $term_id;
		}
		return $ids;
	}

	/** @param array<int, mixed> $data */
	private function ensureSimpleProduct( array $data ): void {
		$sku     = self::PREFIX . $data[1];
		$id      = wc_get_product_id_by_sku( $sku );
		$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
		if ( ! $product ) {
			return; }
		$product->set_name( $data[0] );
		$product->set_sku( $sku );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( $data[2] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $data[3] );
		$product->set_featured( $data[10] );
		$product->set_description( 'FICTIONAL DEVELOPMENT PRODUCT. Replace with approved catalog copy before production.' );
		$product->update_meta_data( '_compadres_fixture', '1' );
		$product->update_meta_data( 'total_sales', $data[11] );
		$meta = ProductData::sanitize(
			array(
				'strength'                  => $data[4],
				'wrapper'                   => $data[5],
				'vitola'                    => $data[6],
				'pack_quantity'             => $data[7],
				'box_quantity'              => $data[8],
				'country_of_origin'         => 'Fictional development origin',
				'flavor_profile'            => 'Fictional development profile',
				'sales_tax_classification'  => 'development-only',
				'excise_tax_classification' => 'development-only',
			)
		);
		foreach ( $meta as $key => $value ) {
			$product->update_meta_data( ProductMetadata::PREFIX . $key, $value ); }
		$product_id = $product->save();
		wp_set_object_terms( $product_id, array( (int) $data[9] ), BrandTaxonomy::TAXONOMY );
		if ( str_contains( $data[1], 'SAMPLER' ) ) {
			wp_set_object_terms( $product_id, array( $this->ensureCategory( 'samplers', 'Fictional Development Samplers' ) ), 'product_cat' ); }
	}

	private function ensureVariableProduct( int $brand_id ): void {
		$sku     = self::PREFIX . 'LANTERN-VARIABLE';
		$id      = wc_get_product_id_by_sku( $sku );
		$product = $id ? wc_get_product( $id ) : new WC_Product_Variable();
		if ( ! $product instanceof WC_Product_Variable ) {
			return; }
		$product->set_name( 'Lantern House Fictional Corona Format' );
		$product->set_sku( $sku );
		$product->set_status( 'publish' );
		$product->set_description( 'FICTIONAL DEVELOPMENT VARIABLE PRODUCT.' );
		$product->update_meta_data( '_compadres_fixture', '1' );
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Pack format' );
		$attribute->set_options( array( 'Single', 'Five pack' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );
		$product_id = $product->save();
		wp_set_object_terms( $product_id, array( $brand_id ), BrandTaxonomy::TAXONOMY );
		foreach ( array(
			'Single'    => array( '9.25', 1 ),
			'Five pack' => array( '43.00', 5 ),
		) as $option => $variation_data ) {
			$variation_sku = $sku . '-' . strtoupper( str_replace( ' ', '-', $option ) );
			$variation_id  = wc_get_product_id_by_sku( $variation_sku );
			$variation     = $variation_id ? wc_get_product( $variation_id ) : new WC_Product_Variation();
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue; }
			$variation->set_parent_id( $product_id );
			$variation->set_sku( $variation_sku );
			$variation->set_regular_price( $variation_data[0] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 10 );
			$variation->set_attributes( array( 'pack-format' => $option ) );
			$variation->update_meta_data( '_compadres_fixture', '1' );
			$variation->update_meta_data( ProductMetadata::PREFIX . 'pack_quantity', (string) $variation_data[1] );
			$variation->save();
		}
		WC_Product_Variable::sync( $product_id );
	}

	private function ensureCategory( string $slug, string $name ): int {
		$term = term_exists( $slug, 'product_cat' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) ); }
		if ( is_wp_error( $term ) ) {
			WP_CLI::error( $term->get_error_message() ); }
		return (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}

	private function allowed(): bool {
		$environment = strtolower( (string) getenv( 'APP_ENV' ) );
		return in_array( $environment, array( 'local', 'development' ), true ) || ( 'staging' === $environment && '1' === getenv( 'COMPADRES_ENABLE_FIXTURES' ) );
	}
}
