<?php

declare(strict_types=1);

namespace Compadres\Commerce\Catalog;

use WP_Term;

final class BrandTaxonomy {

	public const TAXONOMY      = 'compadres_brand';
	private const NONCE_ACTION = 'compadres_save_brand';
	private const NONCE_NAME   = 'compadres_brand_nonce';

	public function registerHooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'renderAddFields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'renderEditFields' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'saveFields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'saveFields' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( $this, 'adminColumns' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( $this, 'adminColumnValue' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminMedia' ) );
		add_shortcode( 'compadres_brands', array( $this, 'brandsShortcode' ) );
		add_filter( 'template_include', array( $this, 'brandArchiveTemplate' ), 99 );
	}

	public function register(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array(
				'labels'             => array(
					'name'          => __( 'Cigar Brands', 'compadres-commerce' ),
					'singular_name' => __( 'Cigar Brand', 'compadres-commerce' ),
					'add_new_item'  => __( 'Add New Cigar Brand', 'compadres-commerce' ),
					'edit_item'     => __( 'Edit Cigar Brand', 'compadres-commerce' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rewrite'            => array(
					'slug'       => 'brands',
					'with_front' => false,
				),
				'capabilities'       => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			)
		);

		foreach ( $this->metaSchema() as $key => $schema ) {
			register_term_meta( self::TAXONOMY, 'compadres_' . $key, $schema );
		}
	}

	/** @return array<string, array<string, mixed>> */
	private function metaSchema(): array {
		$text    = array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => array( $this, 'canEditBrands' ),
		);
		$integer = array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => array( $this, 'canEditBrands' ),
		);
		return array(
			'short_description'    => $text,
			'logo_attachment_id'   => $integer,
			'hero_attachment_id'   => $integer,
			'accent_color'         => $text,
			'brand_story'          => $text,
			'seo_title'            => $text,
			'meta_description'     => $text,
			'featured_product_ids' => array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'auth_callback' => array( $this, 'canEditBrands' ),
			),
			'display_order'        => $integer,
			'active'               => array(
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => array( $this, 'canEditBrands' ),
			),
			'future_odoo_id'       => $text,
			'future_wholesale_id'  => $text,
		);
	}

	public function canEditBrands(): bool {
		return current_user_can( 'manage_product_terms' );
	}

	public function renderAddFields(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$this->renderFields( array(), false );
	}

	public function renderEditFields( WP_Term $term ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$values = array();
		foreach ( array_keys( $this->metaSchema() ) as $key ) {
			$values[ $key ] = get_term_meta( $term->term_id, 'compadres_' . $key, true );
		}
		$this->renderFields( $values, true );
	}

	/** @param array<string, mixed> $values */
	private function renderFields( array $values, bool $table_rows ): void {
		$fields = array(
			'short_description'    => array( 'Short description', 'textarea' ),
			'logo_attachment_id'   => array( 'Logo attachment ID', 'media' ),
			'hero_attachment_id'   => array( 'Hero image attachment ID', 'media' ),
			'accent_color'         => array( 'Accent color', 'color' ),
			'brand_story'          => array( 'Brand story', 'textarea' ),
			'seo_title'            => array( 'SEO title', 'text' ),
			'meta_description'     => array( 'Meta description', 'textarea' ),
			'featured_product_ids' => array( 'Featured product IDs (comma-separated)', 'text' ),
			'display_order'        => array( 'Display order', 'number' ),
			'active'               => array( 'Active', 'checkbox' ),
			'future_odoo_id'       => array( 'Future Odoo brand ID', 'text' ),
			'future_wholesale_id'  => array( 'Future wholesale brand ID', 'text' ),
		);
		foreach ( $fields as $key => $field ) {
			$value  = $values[ $key ] ?? ( 'active' === $key );
			$open   = $table_rows ? '<tr class="form-field"><th scope="row">' : '<div class="form-field">';
			$middle = $table_rows ? '</th><td>' : '';
			$close  = $table_rows ? '</td></tr>' : '</div>';
			echo $open . '<label for="compadres_' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label>' . $middle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->renderInput( $key, $field[1], $value );
			echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function renderInput( string $key, string $type, mixed $value ): void {
		$name = 'compadres_brand[' . $key . ']';
		if ( 'textarea' === $type ) {
			echo '<textarea id="compadres_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( (string) $value ) . '</textarea>';
			return;
		}
		if ( 'checkbox' === $type ) {
			echo '<input id="compadres_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" type="checkbox" value="1" ' . checked( (bool) $value, true, false ) . '>';
			return;
		}
		$input_type = 'media' === $type ? 'number' : $type;
		$display    = is_array( $value ) ? implode( ',', $value ) : (string) $value;
		echo '<input id="compadres_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $input_type ) . '" value="' . esc_attr( $display ) . '">';
		if ( 'media' === $type ) {
			echo '<button type="button" class="button compadres-select-media" data-target="compadres_' . esc_attr( $key ) . '">' . esc_html__( 'Select from Media Library', 'compadres-commerce' ) . '</button>';
		}
	}

	public function saveFields( int $term_id ): void {
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! $this->canEditBrands() ) {
			return;
		}
		$raw = isset( $_POST['compadres_brand'] ) && is_array( $_POST['compadres_brand'] ) ? wp_unslash( $_POST['compadres_brand'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $raw['featured_product_ids'] ) ) {
			$raw['featured_product_ids'] = explode( ',', (string) $raw['featured_product_ids'] );
		}
		$clean = BrandData::sanitize( $raw );
		foreach ( $clean as $key => $value ) {
			update_term_meta( $term_id, 'compadres_' . $key, $value );
		}
	}

	/**
	 * @param  array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function adminColumns( array $columns ): array {
		$columns['compadres_active'] = __( 'Active', 'compadres-commerce' );
		$columns['compadres_order']  = __( 'Order', 'compadres-commerce' );
		$columns['compadres_logo']   = __( 'Logo ID', 'compadres-commerce' );
		return $columns;
	}

	public function adminColumnValue( string $value, string $column, int $term_id ): string {
		return match ( $column ) {
			'compadres_active' => get_term_meta( $term_id, 'compadres_active', true ) ? __( 'Yes', 'compadres-commerce' ) : __( 'No', 'compadres-commerce' ),
			'compadres_order' => (string) absint( get_term_meta( $term_id, 'compadres_display_order', true ) ),
			'compadres_logo' => (string) absint( get_term_meta( $term_id, 'compadres_logo_attachment_id', true ) ),
			default => $value,
		};
	}

	public function enqueueAdminMedia( string $hook ): void {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || self::TAXONOMY !== ( $_GET['taxonomy'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'compadres-brand-admin', plugins_url( 'assets/js/admin-brand.js', dirname( __DIR__, 2 ) . '/compadres-commerce.php' ), array( 'jquery' ), '0.1.0', true );
	}

	public function brandsShortcode(): string {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => 'compadres_active',
				'meta_value' => '1',
				'orderby'    => 'meta_value_num',
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return '<p>' . esc_html__( 'No active brands are published yet.', 'compadres-commerce' ) . '</p>';
		}
		$html = '<div class="brand-grid">';
		foreach ( $terms as $term ) {
			$html .= '<a class="brand-card" href="' . esc_url( get_term_link( $term ) ) . '"><h2>' . esc_html( $term->name ) . '</h2><p>' . esc_html( wp_trim_words( $term->description, 24 ) ) . '</p></a>';
		}
		return $html . '</div>';
	}

	public function brandArchiveTemplate( string $template ): string {
		if ( ! is_tax( self::TAXONOMY ) ) {
			return $template;
		}
		$brand_template = locate_template( 'taxonomy-' . self::TAXONOMY . '.php' );
		return '' !== $brand_template ? $brand_template : $template;
	}
}
