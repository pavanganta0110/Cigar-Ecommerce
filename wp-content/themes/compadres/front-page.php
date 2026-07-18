<?php
/**
 * Data-driven storefront homepage.
 *
 * @package Compadres
 */

get_header();
$hero_image_id = absint( get_theme_mod( 'compadres_hero_image', 0 ) );
$brand_terms   = taxonomy_exists( 'compadres_brand' ) ? get_terms(
	array(
		'taxonomy'   => 'compadres_brand',
		'hide_empty' => true,
		'number'     => 6,
		'meta_key'   => 'compadres_display_order',
		'orderby'    => 'meta_value_num',
	)
) : array();
?>
<main id="main-content">
	<section class="hero">
		<div class="hero__content">
			<p class="eyebrow"><?php esc_html_e( 'Compadres Cigars', 'compadres' ); ?></p>
			<h1><?php echo esc_html( get_theme_mod( 'compadres_hero_heading', 'A shared table. A considered smoke.' ) ); ?></h1>
			<p><?php echo esc_html( get_theme_mod( 'compadres_hero_description', 'Explore a unified collection of premium cigar brands.' ) ); ?></p>
			<a class="button button--primary" href="<?php echo esc_url( get_theme_mod( 'compadres_cta_url', home_url( '/shop/' ) ) ); ?>"><?php echo esc_html( get_theme_mod( 'compadres_cta_label', 'Shop all cigars' ) ); ?></a>
		</div>
		<div class="hero__art">
			<?php
			if ( $hero_image_id ) {
				echo wp_get_attachment_image(
					$hero_image_id,
					'full',
					false,
					array(
						'loading'       => 'eager',
						'fetchpriority' => 'high',
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<span class="screen-reader-text">' . esc_html__( 'Placeholder artwork pending approved brand photography.', 'compadres' ) . '</span>';
			}
			?>
		</div>
	</section>

	<section class="section section--light shop-intro">
		<p class="eyebrow"><?php esc_html_e( 'Unified catalog', 'compadres' ); ?></p>
		<h2><?php esc_html_e( 'Shop all cigars', 'compadres' ); ?></h2>
		<p><?php esc_html_e( 'Browse every active cigar brand through one shared catalog, cart, checkout, inventory, and customer account.', 'compadres' ); ?></p>
		<a class="text-link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the full catalog', 'compadres' ); ?></a>
	</section>

	<section class="section section--dark" aria-labelledby="featured-brands-heading">
		<p class="eyebrow"><?php esc_html_e( 'One house · Distinct labels', 'compadres' ); ?></p>
		<h2 id="featured-brands-heading"><?php esc_html_e( 'Featured brands', 'compadres' ); ?></h2>
		<?php if ( ! is_wp_error( $brand_terms ) && $brand_terms ) : ?>
			<div class="brand-grid">
				<?php foreach ( $brand_terms as $brand ) : ?>
					<a class="brand-card" href="<?php echo esc_url( get_term_link( $brand ) ); ?>">
						<h3><?php echo esc_html( $brand->name ); ?></h3>
						<p><?php echo esc_html( wp_trim_words( $brand->description, 24 ) ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="empty-catalog"><?php esc_html_e( 'Catalog content is being prepared. Fictional development fixtures can be loaded locally for testing.', 'compadres' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section product-section" aria-labelledby="featured-products-heading">
		<h2 id="featured-products-heading"><?php esc_html_e( 'Featured cigars', 'compadres' ); ?></h2>
		<?php
		if (
			compadres_has_products(
				array(
					'featured' => true,
					'status'   => 'publish',
				)
			)
		) :
			?>
			<?php echo do_shortcode( '[products limit="4" columns="4" visibility="featured"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="empty-catalog"><?php esc_html_e( 'Catalog content is being prepared. No featured cigars are published yet.', 'compadres' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section product-section" aria-labelledby="best-sellers-heading">
		<h2 id="best-sellers-heading"><?php esc_html_e( 'Best sellers', 'compadres' ); ?></h2>
		<?php if ( compadres_has_products( array( 'status' => 'publish' ) ) ) : ?>
			<?php echo do_shortcode( '[best_selling_products limit="4" columns="4"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="empty-catalog"><?php esc_html_e( 'Catalog content is being prepared. Sales rankings appear only after real or fictional development orders exist.', 'compadres' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section product-section" aria-labelledby="new-releases-heading">
		<h2 id="new-releases-heading"><?php esc_html_e( 'New releases', 'compadres' ); ?></h2>
		<?php if ( compadres_has_products( array( 'status' => 'publish' ) ) ) : ?>
			<?php echo do_shortcode( '[recent_products limit="4" columns="4"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="empty-catalog"><?php esc_html_e( 'Catalog content is being prepared. New releases will be populated from published products.', 'compadres' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section product-section" aria-labelledby="samplers-heading">
		<h2 id="samplers-heading"><?php esc_html_e( 'Samplers', 'compadres' ); ?></h2>
		<?php
		if (
			compadres_has_products(
				array(
					'status'   => 'publish',
					'category' => array( 'samplers' ),
				)
			)
		) :
			?>
			<?php echo do_shortcode( '[product_category category="samplers" limit="4" columns="4"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<p class="empty-catalog"><?php esc_html_e( 'Catalog content is being prepared. Samplers will appear from the published sampler category.', 'compadres' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="section story-panel">
		<p class="eyebrow"><?php esc_html_e( 'Craft and company story', 'compadres' ); ?></p>
		<h2><?php esc_html_e( 'Approved story content coming soon', 'compadres' ); ?></h2>
		<p><?php esc_html_e( 'This section is ready for approved information about Compadres, its people, and its approach. No company history or production claims are invented during development.', 'compadres' ); ?></p>
		<a class="text-link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About Compadres', 'compadres' ); ?></a>
	</section>

	<section class="section notice-panel" aria-labelledby="purchase-notice-heading">
		<h2 id="purchase-notice-heading"><?php esc_html_e( 'Before you purchase', 'compadres' ); ?></h2>
		<p><?php echo esc_html( get_theme_mod( 'compadres_age_notice', 'For adults 21 and older. Site entry confirmation does not replace checkout identity verification.' ) ); ?></p>
		<p><?php echo esc_html( get_theme_mod( 'compadres_shipping_notice', 'Availability and shipping eligibility are confirmed during checkout.' ) ); ?></p>
	</section>

	<section class="section signup-panel" aria-labelledby="signup-heading">
		<h2 id="signup-heading"><?php esc_html_e( 'Email updates', 'compadres' ); ?></h2>
		<p><?php esc_html_e( 'Signup will be enabled only after a consent-compliant email provider and approved messaging are configured.', 'compadres' ); ?></p>
		<span class="status-badge"><?php esc_html_e( 'Not configured', 'compadres' ); ?></span>
	</section>
</main>
<?php get_footer(); ?>
