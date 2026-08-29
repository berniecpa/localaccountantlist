<?php
/**
 * Front page: hero search, featured (boosted) listings, latest listings,
 * vehicle-type tiles, get-listed CTA.
 */

get_header();
?>

<section class="llh-hero">
	<h1><?php esc_html_e( 'Arrive in', 'limolisthone' ); ?> <em><?php esc_html_e( 'style', 'limolisthone' ); ?></em></h1>
	<p class="llh-hero__sub"><?php esc_html_e( 'Compare trusted limousine and chauffeur services near you — stretch limos, party buses, executive sedans, and more.', 'limolisthone' ); ?></p>
	<?php llh_filter_form(); ?>
</section>

<?php
$featured = new WP_Query(
	array(
		'post_type'      => 'limo_listing',
		'posts_per_page' => 3,
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => '_llh_boost_active',
				'value' => '1',
			),
		),
	)
);
if ( $featured->have_posts() ) :
	?>
	<section class="llh-section">
		<div class="llh-container">
			<div class="llh-section__head">
				<h2><?php esc_html_e( 'Featured services', 'limolisthone' ); ?></h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'limo_listing' ) ); ?>"><?php esc_html_e( 'View all listings &rarr;', 'limolisthone' ); ?></a>
			</div>
			<div class="llh-grid">
				<?php
				while ( $featured->have_posts() ) {
					$featured->the_post();
					get_template_part( 'template-parts/listing-card' );
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
$types = get_terms(
	array(
		'taxonomy'   => 'vehicle_type',
		'hide_empty' => true,
		'number'     => 8,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
if ( ! is_wp_error( $types ) && $types ) :
	?>
	<section class="llh-section llh-section--alt">
		<div class="llh-container">
			<div class="llh-section__head">
				<h2><?php esc_html_e( 'Browse by vehicle', 'limolisthone' ); ?></h2>
			</div>
			<div class="llh-tiles">
				<?php foreach ( $types as $type ) : ?>
					<a class="llh-tile" href="<?php echo esc_url( get_term_link( $type ) ); ?>">
						<?php echo esc_html( $type->name ); ?>
						<span>
							<?php
							/* translators: %d: listing count */
							printf( esc_html( _n( '%d listing', '%d listings', (int) $type->count, 'limolisthone' ) ), (int) $type->count );
							?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
$latest = new WP_Query(
	array(
		'post_type'      => 'limo_listing',
		'posts_per_page' => 6,
		'no_found_rows'  => true,
	)
);
if ( $latest->have_posts() ) :
	?>
	<section class="llh-section">
		<div class="llh-container">
			<div class="llh-section__head">
				<h2><?php esc_html_e( 'Latest listings', 'limolisthone' ); ?></h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'limo_listing' ) ); ?>"><?php esc_html_e( 'View all &rarr;', 'limolisthone' ); ?></a>
			</div>
			<div class="llh-grid">
				<?php
				while ( $latest->have_posts() ) {
					$latest->the_post();
					get_template_part( 'template-parts/listing-card' );
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="llh-section llh-section--alt">
	<div class="llh-container" style="text-align:center;">
		<h2><?php esc_html_e( 'Run a limo service?', 'limolisthone' ); ?></h2>
		<p style="color:var(--llh-muted);max-width:560px;margin:0 auto 24px;">
			<?php esc_html_e( 'List your fleet for free and reach customers planning weddings, proms, nights out, and corporate travel. Boost your listing to appear first in every search.', 'limolisthone' ); ?>
		</p>
		<?php $submit_page = get_page_by_path( 'get-listed' ); ?>
		<a class="llh-button" href="<?php echo esc_url( $submit_page ? get_permalink( $submit_page ) : get_post_type_archive_link( 'limo_listing' ) ); ?>">
			<?php esc_html_e( 'Get listed today', 'limolisthone' ); ?>
		</a>
	</div>
</section>

<?php get_footer(); ?>
