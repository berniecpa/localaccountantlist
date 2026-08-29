<?php
/**
 * Generic fallback template: blog index, generic archives, search results.
 */

get_header();
?>
<div class="llh-container llh-section">
	<div class="llh-section__head">
		<h2>
			<?php
			if ( is_search() ) {
				/* translators: %s: search query */
				printf( esc_html__( 'Search results for “%s”', 'limolisthone' ), esc_html( get_search_query() ) );
			} elseif ( is_archive() ) {
				the_archive_title();
			} else {
				esc_html_e( 'Latest posts', 'limolisthone' );
			}
			?>
		</h2>
	</div>

	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			if ( 'limo_listing' === get_post_type() ) {
				echo '<div class="llh-grid" style="margin-bottom:24px;">';
				get_template_part( 'template-parts/listing-card' );
				echo '</div>';
				continue;
			}
			?>
			<article <?php post_class(); ?> style="border-bottom:1px solid var(--llh-line);padding:24px 0;">
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<div class="llh-prose"><?php the_excerpt(); ?></div>
			</article>
		<?php endwhile; ?>

		<nav class="llh-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'limolisthone' ); ?>">
			<?php echo paginate_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</nav>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'limolisthone' ); ?></p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
