<?php
/** Front page. */
get_header();
?>
<main id="main-content">
	<section class="hero">
		<div class="hero__content">
			<p class="eyebrow"><?php esc_html_e( 'Compadres Cigars · Preview Experience', 'compadres' ); ?></p>
			<h1><?php esc_html_e( 'A shared table. A considered smoke.', 'compadres' ); ?></h1>
			<p><?php esc_html_e( 'Explore a unified collection of premium cigar brands. Product stories, photography, and claims shown during development are placeholders until approved.', 'compadres' ); ?></p>
			<a class="button button--primary" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Explore the collection', 'compadres' ); ?></a>
		</div>
		<div class="hero__art" role="img" aria-label="<?php esc_attr_e( 'Abstract warm wood and tobacco-toned composition; placeholder artwork', 'compadres' ); ?>"></div>
	</section>
	<section class="section section--light">
		<p class="eyebrow"><?php esc_html_e( 'One house · Many labels', 'compadres' ); ?></p>
		<h2><?php esc_html_e( 'Distinct brands, one seamless humidor', 'compadres' ); ?></h2>
		<p><?php esc_html_e( 'Every brand will have its own story and visual identity while sharing one secure cart, checkout, inventory, and account system.', 'compadres' ); ?></p>
		<?php echo do_shortcode( '[products limit="4" columns="4" visibility="featured"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
</main>
<?php get_footer(); ?>
