<?php
/**
 * LimoListHone theme setup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function llh_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
	add_theme_support( 'automatic-feed-links' );

	add_image_size( 'llh-card', 640, 427, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'limolisthone' ),
			'footer'  => __( 'Footer Menu', 'limolisthone' ),
		)
	);
}
add_action( 'after_setup_theme', 'llh_theme_setup' );

function llh_theme_assets() {
	wp_enqueue_style( 'limolisthone', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_script( 'limolisthone', get_template_directory_uri() . '/assets/theme.js', array(), wp_get_theme()->get( 'Version' ), true );

	if ( is_singular() && comments_open() ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'llh_theme_assets' );

/**
 * Default primary menu when none is assigned: directory + submission links.
 */
function llh_default_menu() {
	echo '<ul>';
	printf( '<li><a href="%s">%s</a></li>', esc_url( get_post_type_archive_link( 'limo_listing' ) ), esc_html__( 'Browse Limos', 'limolisthone' ) );
	$submit = get_page_by_path( 'get-listed' );
	if ( $submit ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $submit ) ), esc_html__( 'Get Listed', 'limolisthone' ) );
	}
	echo '</ul>';
}

/**
 * The listing search/filter form. Used in the hero and the archive sidebar.
 *
 * @param bool $stacked Render fields stacked (sidebar) instead of as a bar.
 */
function llh_filter_form( $stacked = false ) {
	$filters = class_exists( 'LLH_Search' ) ? LLH_Search::current_filters() : array( 'keyword' => '', 'area' => '', 'vehicle' => '', 'capacity' => 0 );
	$areas    = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => true ) );
	$vehicles = get_terms( array( 'taxonomy' => 'vehicle_type', 'hide_empty' => true ) );
	$action   = get_post_type_archive_link( 'limo_listing' );
	?>
	<form class="<?php echo $stacked ? 'llh-form' : 'llh-searchbar'; ?>" method="get" action="<?php echo esc_url( $action ); ?>">
		<p>
			<?php if ( $stacked ) : ?><label for="llh_s"><?php esc_html_e( 'Keyword', 'limolisthone' ); ?></label><?php endif; ?>
			<input type="text" id="llh_s" name="llh_s" value="<?php echo esc_attr( $filters['keyword'] ); ?>"
				placeholder="<?php esc_attr_e( 'Company or keyword…', 'limolisthone' ); ?>" />
		</p>
		<p>
			<?php if ( $stacked ) : ?><label for="llh_area"><?php esc_html_e( 'Service area', 'limolisthone' ); ?></label><?php endif; ?>
			<select id="llh_area" name="llh_area">
				<option value=""><?php esc_html_e( 'All areas', 'limolisthone' ); ?></option>
				<?php if ( ! is_wp_error( $areas ) ) : foreach ( $areas as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['area'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; endif; ?>
			</select>
		</p>
		<p>
			<?php if ( $stacked ) : ?><label for="llh_vehicle"><?php esc_html_e( 'Vehicle type', 'limolisthone' ); ?></label><?php endif; ?>
			<select id="llh_vehicle" name="llh_vehicle">
				<option value=""><?php esc_html_e( 'All vehicles', 'limolisthone' ); ?></option>
				<?php if ( ! is_wp_error( $vehicles ) ) : foreach ( $vehicles as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['vehicle'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; endif; ?>
			</select>
		</p>
		<?php if ( $stacked ) : ?>
			<p>
				<label for="llh_capacity"><?php esc_html_e( 'Minimum passengers', 'limolisthone' ); ?></label>
				<input type="number" id="llh_capacity" name="llh_capacity" min="1"
					value="<?php echo $filters['capacity'] ? (int) $filters['capacity'] : ''; ?>" />
			</p>
		<?php endif; ?>
		<p><button type="submit" class="llh-button"><?php echo $stacked ? esc_html__( 'Apply filters', 'limolisthone' ) : esc_html__( 'Search', 'limolisthone' ); ?></button></p>
	</form>
	<?php
}

/**
 * Listing meta line for cards: primary service area + capacity.
 */
function llh_card_meta( $listing_id ) {
	$bits = array();

	$areas = get_the_term_list( $listing_id, 'service_area', '', ', ' );
	if ( $areas && ! is_wp_error( $areas ) ) {
		$bits[] = '<span>&#128205; ' . wp_kses_post( $areas ) . '</span>';
	}

	$capacity = (int) get_post_meta( $listing_id, '_llh_max_capacity', true );
	if ( $capacity ) {
		/* translators: %d: passenger count */
		$bits[] = '<span>&#128101; ' . esc_html( sprintf( __( 'Up to %d', 'limolisthone' ), $capacity ) ) . '</span>';
	}

	$price = get_post_meta( $listing_id, '_llh_price_range', true );
	if ( $price ) {
		$bits[] = '<span>' . esc_html( $price ) . '</span>';
	}

	if ( $bits ) {
		echo '<div class="llh-card__meta">' . implode( '', $bits ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
