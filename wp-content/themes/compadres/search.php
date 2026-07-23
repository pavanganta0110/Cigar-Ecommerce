<?php
/**
 * Search results template.
 *
 * @package Compadres
 */

get_header();
$query = get_search_query();
?>
<main id="main-content" class="content-shell search-shell">
	<header class="page-header">
		<p class="eyebrow"><?php esc_html_e( 'Catalog and journal', 'compadres' ); ?></p>
		<h1>
			<?php
			printf(
				/* translators: %s: search query. */
				esc_html__( 'Search results for “%s”', 'compadres' ),
				esc_html( $query )
			);
			?>
		</h1>
		<p>
			<?php
			global $wp_query;
			printf(
				/* translators: %d: number of results. */
				esc_html( _n( '%d result', '%d results', (int) $wp_query->found_posts, 'compadres' ) ),
				(int) $wp_query->found_posts
			);
			?>
		</p>
		<?php get_search_form(); ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="search-results-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'search-result-card' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<section class="empty-state" aria-labelledby="no-results-heading">
			<h2 id="no-results-heading"><?php esc_html_e( 'No results found', 'compadres' ); ?></h2>
			<p><?php esc_html_e( 'Try another search or browse the complete cigar catalog.', 'compadres' ); ?></p>
			<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Browse the shop', 'compadres' ); ?></a>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
