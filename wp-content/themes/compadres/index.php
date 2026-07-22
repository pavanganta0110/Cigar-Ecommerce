<?php
/** Default template. */
get_header();
?>
<main id="main-content" class="content-shell">
<?php if ( have_posts() ) : ?>
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
	<?php the_posts_pagination(); ?>
<?php else : ?>
	<p><?php esc_html_e( 'Nothing was found.', 'compadres' ); ?></p>
<?php endif; ?>
</main>
<?php get_footer(); ?>
