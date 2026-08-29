<?php
/**
 * Listing archive body: filter sidebar + results grid.
 * Shared by archive-limo_listing.php and taxonomy.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;
?>
<div class="llh-container llh-section">
	<div class="llh-section__head">
		<h2>
			<?php
			if ( is_tax() ) {
				single_term_title();
			} else {
				esc_html_e( 'Browse Limo Services', 'limolisthone' );
			}
			?>
		</h2>
	</div>

	<div class="llh-archive-layout">
		<aside class="llh-filters">
			<h3><?php esc_html_e( 'Refine results', 'limolisthone' ); ?></h3>
			<?php llh_filter_form( true ); ?>
		</aside>

		<div>
			<p class="llh-count">
				<?php
				/* translators: %d: number of listings */
				printf( esc_html( _n( '%d listing found', '%d listings found', (int) $wp_query->found_posts, 'limolisthone' ) ), (int) $wp_query->found_posts );
				?>
			</p>

			<?php if ( have_posts() ) : ?>
				<div class="llh-grid llh-results-grid">
					<?php
					while ( have_posts() ) {
						the_post();
						get_template_part( 'template-parts/listing-card' );
					}
					?>
				</div>

				<nav class="llh-pagination" aria-label="<?php esc_attr_e( 'Listings pagination', 'limolisthone' ); ?>">
					<?php echo paginate_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</nav>
			<?php else : ?>
				<p><?php esc_html_e( 'No listings match those filters yet. Try widening your search.', 'limolisthone' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
