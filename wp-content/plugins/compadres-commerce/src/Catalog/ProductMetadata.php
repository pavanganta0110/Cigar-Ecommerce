<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use InvalidArgumentException;
use WC_Product;

final class ProductMetadata {

	public const PREFIX = '_compadres_';

	/** @return array<string, array{label:string,type:string}> */
	public static function fields(): array {
		return array(
			'product_line'              => array(
				'label' => 'Product line',
				'type'  => 'text',
			),
			'upc'                       => array(
				'label' => 'UPC',
				'type'  => 'text',
			),
			'country_of_origin'         => array(
				'label' => 'Country of origin',
				'type'  => 'text',
			),
			'wrapper'                   => array(
				'label' => 'Wrapper',
				'type'  => 'text',
			),
			'binder'                    => array(
				'label' => 'Binder',
				'type'  => 'text',
			),
			'filler'                    => array(
				'label' => 'Filler',
				'type'  => 'textarea',
			),
			'strength'                  => array(
				'label' => 'Strength',
				'type'  => 'select',
			),
			'flavor_profile'            => array(
				'label' => 'Flavor profile',
				'type'  => 'textarea',
			),
			'vitola'                    => array(
				'label' => 'Vitola',
				'type'  => 'text',
			),
			'length'                    => array(
				'label' => 'Length (inches)',
				'type'  => 'number',
			),
			'ring_gauge'                => array(
				'label' => 'Ring gauge',
				'type'  => 'number',
			),
			'pack_quantity'             => array(
				'label' => 'Pack quantity',
				'type'  => 'number',
			),
			'box_quantity'              => array(
				'label' => 'Box quantity',
				'type'  => 'number',
			),
			'sales_tax_classification'  => array(
				'label' => 'Sales-tax classification',
				'type'  => 'text',
			),
			'excise_tax_classification' => array(
				'label' => 'Excise-tax classification',
				'type'  => 'text',
			),
			'restricted_jurisdictions'  => array(
				'label' => 'Restricted-jurisdiction metadata',
				'type'  => 'textarea',
			),
			'future_odoo_id'            => array(
				'label' => 'Future Odoo product ID',
				'type'  => 'text',
			),
			'future_wholesale_id'       => array(
				'label' => 'Future wholesale product ID',
				'type'  => 'text',
			),
		);
	}

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'registerRestMeta' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'addProductTab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'renderProductPanel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'saveProduct' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'renderVariationFields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'saveVariation' ), 10, 2 );
		add_filter( 'woocommerce_product_tabs', array( $this, 'addSpecificationTab' ) );
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'exportColumns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'exportColumns' ) );
		add_filter( 'woocommerce_product_export_product_column_compadres_brand', array( $this, 'exportBrand' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'importMappingOptions' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'importDefaultMappings' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'applyImportedMetadata' ), 10, 2 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'assignImportedBrand' ), 10, 2 );
		foreach ( array_keys( self::fields() ) as $field ) {
			add_filter( 'woocommerce_product_export_product_column_compadres_' . $field, fn ( mixed $value, WC_Product $product ): mixed => $product->get_meta( self::PREFIX . $field ), 10, 2 );
		}
	}

	public function registerRestMeta(): void {
		foreach ( self::fields() as $field => $definition ) {
			$type = in_array( $field, array( 'ring_gauge', 'pack_quantity', 'box_quantity' ), true ) ? 'integer' : 'string';
			register_post_meta(
				'product',
				self::PREFIX . $field,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn (): bool => current_user_can( 'edit_products' ),
				)
			);
		}
	}

	/**
	 * @param  array<string, mixed> $tabs Product-data tabs.
	 * @return array<string, mixed>
	 */
	public function addProductTab( array $tabs ): array {
		$tabs['compadres_cigar'] = array(
			'label'    => __( 'Cigar Details', 'compadres-commerce' ),
			'target'   => 'compadres_cigar_data',
			'class'    => array(),
			'priority' => 45,
		);
		return $tabs;
	}

	public function renderProductPanel(): void {
		echo '<div id="compadres_cigar_data" class="panel woocommerce_options_panel">';
		foreach ( self::fields() as $field => $definition ) {
			$args = array(
				'id'    => self::PREFIX . $field,
				'label' => $definition['label'],
			);
			if ( 'select' === $definition['type'] ) {
				$args['options'] = array(
					''            => __( 'Not specified', 'compadres-commerce' ),
					'mild'        => __( 'Mild', 'compadres-commerce' ),
					'mild-medium' => __( 'Mild–Medium', 'compadres-commerce' ),
					'medium'      => __( 'Medium', 'compadres-commerce' ),
					'medium-full' => __( 'Medium–Full', 'compadres-commerce' ),
					'full'        => __( 'Full', 'compadres-commerce' ),
				);
				woocommerce_wp_select( $args );
			} elseif ( 'textarea' === $definition['type'] ) {
				woocommerce_wp_textarea_input( $args );
			} else {
				$args['type'] = $definition['type'];
				woocommerce_wp_text_input( $args );
			}
		}
		echo '</div>';
	}

	public function saveProduct( WC_Product $product ): void {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		$raw = array();
		foreach ( array_keys( self::fields() ) as $field ) {
			$raw[ $field ] = isset( $_POST[ self::PREFIX . $field ] ) ? wp_unslash( $_POST[ self::PREFIX . $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		try {
			$this->saveClean( $product, ProductData::sanitize( $raw ) );
		} catch ( InvalidArgumentException $exception ) {
			\WC_Admin_Meta_Boxes::add_error( esc_html( $exception->getMessage() ) );
		}
	}

	/** @param array<string, mixed> $clean */
	private function saveClean( WC_Product $product, array $clean ): void {
		foreach ( $clean as $field => $value ) {
			$product->update_meta_data( self::PREFIX . $field, $value );
		}
	}

	/** @param array<string, mixed> $variation_data Existing variation data. */
	public function renderVariationFields( int $loop, array $variation_data, object $variation ): void {
		foreach ( array(
			'upc'           => 'UPC',
			'pack_quantity' => 'Pack quantity',
			'box_quantity'  => 'Box quantity',
		) as $field => $label ) {
			woocommerce_wp_text_input(
				array(
					'id'            => self::PREFIX . $field . '[' . $loop . ']',
					'label'         => $label,
					'value'         => get_post_meta( $variation->ID, self::PREFIX . $field, true ),
					'wrapper_class' => 'form-row form-row-full',
				)
			);
		}
	}

	public function saveVariation( int $variation_id, int $loop ): void {
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}
		$product = wc_get_product( $variation_id );
		if ( ! $product ) {
			return;
		}
		$raw = array();
		foreach ( array( 'upc', 'pack_quantity', 'box_quantity' ) as $field ) {
			$values        = isset( $_POST[ self::PREFIX . $field ] ) && is_array( $_POST[ self::PREFIX . $field ] ) ? wp_unslash( $_POST[ self::PREFIX . $field ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw[ $field ] = $values[ $loop ] ?? '';
		}
		try {
			$clean = ProductData::sanitize( $raw );
			foreach ( array( 'upc', 'pack_quantity', 'box_quantity' ) as $field ) {
				$product->update_meta_data( self::PREFIX . $field, $clean[ $field ] );
			}
			$product->save();
		} catch ( InvalidArgumentException $exception ) {
			\WC_Admin_Meta_Boxes::add_error( esc_html( $exception->getMessage() ) );
		}
	}

	/**
	 * @param  array<string, mixed> $tabs Product-page tabs.
	 * @return array<string, mixed>
	 */
	public function addSpecificationTab( array $tabs ): array {
		global $product;
		if ( $product instanceof WC_Product && $this->hasSpecifications( $product ) ) {
			$tabs['compadres_specifications'] = array(
				'title'    => __( 'Cigar specifications', 'compadres-commerce' ),
				'priority' => 25,
				'callback' => array( $this, 'renderSpecifications' ),
			);
		}
		return $tabs;
	}

	private function hasSpecifications( WC_Product $product ): bool {
		foreach ( array_keys( self::fields() ) as $field ) {
			if ( '' !== (string) $product->get_meta( self::PREFIX . $field ) ) {
				return true;
			}
		}
		return false;
	}

	public function renderSpecifications(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo '<dl class="compadres-specifications">';
		foreach ( self::fields() as $field => $definition ) {
			$value = $product->get_meta( self::PREFIX . $field );
			if ( '' !== (string) $value && ! in_array( $field, array( 'restricted_jurisdictions', 'future_odoo_id', 'future_wholesale_id', 'sales_tax_classification', 'excise_tax_classification', 'upc' ), true ) ) {
				echo '<div><dt>' . esc_html( $definition['label'] ) . '</dt><dd>' . esc_html( (string) $value ) . '</dd></div>';
			}
		}
		echo '</dl>';
	}

	/**
	 * @param  array<string, string> $columns Export columns.
	 * @return array<string, string>
	 */
	public function exportColumns( array $columns ): array {
		$columns['compadres_brand'] = __( 'Brand', 'compadres-commerce' );
		foreach ( self::fields() as $field => $definition ) {
			$columns[ 'compadres_' . $field ] = $definition['label'];
		}
		return $columns;
	}

	public function exportBrand( mixed $value, WC_Product $product ): string {
		$terms = wp_get_post_terms( $product->get_id(), BrandTaxonomy::TAXONOMY, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? '' : implode( ', ', $terms );
	}

	/**
	 * @param  array<string, string> $options Import mapping options.
	 * @return array<string, string>
	 */
	public function importMappingOptions( array $options ): array {
		$options['compadres_brand'] = __( 'Compadres brand', 'compadres-commerce' );
		foreach ( self::fields() as $field => $definition ) {
			$options[ 'compadres_' . $field ] = $definition['label'];
		}
		return $options;
	}

	/**
	 * @param  array<string, string> $columns Default header mappings.
	 * @return array<string, string>
	 */
	public function importDefaultMappings( array $columns ): array {
		$columns['product_name']      = 'name';
		$columns['product_type']      = 'type';
		$columns['sku']               = 'sku';
		$columns['brand']             = 'compadres_brand';
		$columns['parent']            = 'parent_id';
		$columns['regular_price']     = 'regular_price';
		$columns['weight']            = 'weight';
		$columns['shipping_length']   = 'length';
		$columns['shipping_width']    = 'width';
		$columns['shipping_height']   = 'height';
		$columns['stock']             = 'stock_quantity';
		$columns['description']       = 'description';
		$columns['short_description'] = 'short_description';
		$columns['categories']        = 'category_ids';
		foreach ( array_keys( self::fields() ) as $field ) {
			$columns[ $field ] = 'compadres_' . $field;
		}
		return $columns;
	}

	/** @param array<string, mixed> $data Parsed import row. */
	public function applyImportedMetadata( WC_Product $product, array $data ): WC_Product {
		$raw = array();
		foreach ( array_keys( self::fields() ) as $field ) {
			$raw[ $field ] = $data[ 'compadres_' . $field ] ?? '';
		}
		$this->saveClean( $product, ProductData::sanitize( $raw ) );
		return $product;
	}

	/** @param array<string, mixed> $data Parsed import row. */
	public function assignImportedBrand( WC_Product $product, array $data ): void {
		$brand = sanitize_text_field( (string) ( $data['compadres_brand'] ?? '' ) );
		if ( '' === $brand ) {
			return;
		}
		$term = term_exists( $brand, BrandTaxonomy::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( $brand, BrandTaxonomy::TAXONOMY );
		}
		if ( ! is_wp_error( $term ) ) {
			$term_id = (int) $term['term_id'];
			wp_set_object_terms( $product->get_id(), array( $term_id ), BrandTaxonomy::TAXONOMY, false );
		}
	}
}
