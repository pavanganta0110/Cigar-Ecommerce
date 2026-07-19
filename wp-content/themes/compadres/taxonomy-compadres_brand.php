<?php
/**
 * Product-brand archive.
 *
 * This is a taxonomy template, not a WooCommerce core template override.
 *
 * @package Compadres
 */

get_header();
$brand         = get_queried_object();
$hero_image_id = $brand instanceof WP_Term ? absint( get_term_meta( $brand->term_id, 'compadres_hero_image_id', true ) ) : 0;
$short         = $brand instanceof WP_Term ? (string) get_term_meta( $brand->term_id, 'compadres_short_description', true ) : '';
$story         = $brand instanceof WP_Term ? (string) get_term_meta( $brand->term_id, 'compadres_brand_story', true ) : '';
$accent        = $brand instanceof WP_Term ? sanitize_hex_color( (string) get_term_meta( $brand->term_id, 'compadres_accent_color', true ) ) : '';
$archive_style = $accent ? 'border-top-color:' . $accent : '';
?>
<main id="main-content" class="brand-archive" style="<?php echo esc_attr( $archive_style ); ?>">
	<header class="brand-hero">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Compadres brand', 'compadres' ); ?></p>
			<h1><?php single_term_title(); ?></h1>
			<?php if ( $short ) : ?>
				<p class="brand-hero__short"><?php echo esc_html( $short ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $hero_image_id ) : ?>
			<?php echo wp_get_attachment_image( $hero_image_id, 'large', false, array( 'class' => 'brand-hero__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</header>

	<?php if ( $brand instanceof WP_Term && $brand->description ) : ?>
		<section class="section brand-description">
			<h2><?php esc_html_e( 'About this brand', 'compadres' ); ?></h2>
			<?php echo wp_kses_post( wpautop( $brand->description ) ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $story ) : ?>
		<section class="section story-panel">
			<h2><?php esc_html_e( 'Brand story', 'compadres' ); ?></h2>
			<?php echo wp_kses_post( wpautop( $story ) ); ?>
		</section>
	<?php endif; ?>

	<section class="content-shell brand-products" aria-labelledby="brand-products-heading">
		<h2 id="brand-products-heading"><?php esc_html_e( 'Cigars from this brand', 'compadres' ); ?></h2>
		<?php
		add_filter( 'woocommerce_show_page_title', '__return_false' );
		woocommerce_content();
		remove_filter( 'woocommerce_show_page_title', '__return_false' );
		?>
	</section>
</main>
<?php get_footer(); ?>
