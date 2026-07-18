<?php
/**
 * Not-found recovery template.
 *
 * @package Compadres
 */

get_header();
?>
<main id="main-content" class="content-shell error-shell">
	<section class="empty-state" aria-labelledby="not-found-heading">
		<p class="eyebrow">404</p>
		<h1 id="not-found-heading"><?php esc_html_e( 'Page not found', 'compadres' ); ?></h1>
		<p><?php esc_html_e( 'The requested page is unavailable. Use search or return to the storefront.', 'compadres' ); ?></p>
		<?php get_search_form(); ?>
		<div class="button-row">
			<a class="button button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'compadres' ); ?></a>
			<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the shop', 'compadres' ); ?></a>
		</div>
	</section>
</main>
<?php get_footer(); ?>
