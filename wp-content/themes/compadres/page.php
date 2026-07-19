<?php
/**
 * Standard page template.
 *
 * @package Compadres
 */

get_header();
?>
<main id="main-content" class="content-shell page-shell">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'prose' ); ?>>
			<header class="page-header">
				<p class="eyebrow"><?php esc_html_e( 'Compadres Cigars', 'compadres' ); ?></p>
				<h1><?php the_title(); ?></h1>
			</header>
			<div class="entry-content">
				<?php if ( function_exists( 'is_checkout' ) && is_checkout() ) : ?>
					<h2 class="screen-reader-text"><?php esc_html_e( 'Checkout details', 'compadres' ); ?></h2>
				<?php endif; ?>
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
