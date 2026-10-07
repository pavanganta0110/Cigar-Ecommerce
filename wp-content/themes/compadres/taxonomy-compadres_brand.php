<?php
/**
 * Product-brand landing page.
 *
 * Content comes from the brand's term fields (tagline, story, logo, hero
 * image, accent color). Each brand can be themed separately in main.css via
 * the .brand-archive--{slug} class. This is a taxonomy template, not a
 * WooCommerce core template override.
 *
 * @package Compadres
 */

get_header();
$brand   = get_queried_object();
$term_id = $brand instanceof WP_Term ? $brand->term_id : 0;
$slug    = $brand instanceof WP_Term ? sanitize_html_class( $brand->slug ) : '';
$meta    = static fn ( string $key ): string => (string) get_term_meta( $term_id, 'compadres_' . $key, true );
$hero_id = absint( $meta( 'hero_attachment_id' ) );
$logo_id = absint( $meta( 'logo_attachment_id' ) );
$short   = $meta( 'short_description' );
$story   = $meta( 'brand_story' );
$accent  = sanitize_hex_color( $meta( 'accent_color' ) );
$style   = $accent ? '--brand-accent:' . $accent . ';border-top-color:' . $accent : '';
?>
<main id="main-content" class="brand-archive brand-archive--<?php echo esc_attr( $slug ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<header class="brand-hero">
		<div class="brand-hero__copy">
			<p class="eyebrow"><?php esc_html_e( 'Compadres brand', 'compadres' ); ?></p>
			<h1><?php single_term_title(); ?></h1>
			<?php if ( $short ) : ?>
				<p class="brand-hero__short"><?php echo esc_html( $short ); ?></p>
			<?php endif; ?>
			<a class="button button--primary brand-hero__cta" href="#brand-products-heading"><?php esc_html_e( 'Buy a box', 'compadres' ); ?></a>
		</div>
		<div class="brand-hero__media">
			<?php if ( $hero_id ) : ?>
				<?php echo wp_get_attachment_image( $hero_id, 'large', false, array( 'class' => 'brand-hero__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( $logo_id ) : ?>
				<?php echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'brand-hero__logo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $story ) : ?>
		<section class="section story-panel brand-story">
			<p class="eyebrow"><?php esc_html_e( 'The story', 'compadres' ); ?></p>
			<h2><?php single_term_title(); ?></h2>
			<?php echo wp_kses_post( wpautop( $story ) ); ?>
		</section>
	<?php elseif ( $brand instanceof WP_Term && $brand->description ) : ?>
		<section class="section brand-description">
			<h2><?php esc_html_e( 'About this brand', 'compadres' ); ?></h2>
			<?php echo wp_kses_post( wpautop( $brand->description ) ); ?>
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

	<ul class="brand-assurances">
		<li><?php esc_html_e( 'Secure checkout', 'compadres' ); ?></li>
		<li><?php esc_html_e( 'Adults 21+ only', 'compadres' ); ?></li>
		<li><?php esc_html_e( 'Adult signature on delivery', 'compadres' ); ?></li>
	</ul>
</main>
<?php get_footer(); ?>
