<?php
/**
 * Listing detail page: gallery, facts panel, quote form, reviews.
 */

get_header();

while ( have_posts() ) :
	the_post();
	$listing_id = get_the_ID();
	$boosted    = function_exists( 'llh_is_boosted' ) && llh_is_boosted( $listing_id );
	$rating     = class_exists( 'LLH_Reviews' ) ? LLH_Reviews::rating( $listing_id ) : array( 'avg' => 0, 'count' => 0 );
	$gallery    = array_filter( array_map( 'absint', (array) get_post_meta( $listing_id, '_llh_gallery', true ) ) );
	?>
	<div class="llh-container llh-section">

		<?php if ( isset( $_GET['llh_boost'] ) && 'success' === $_GET['llh_boost'] ) : ?>
			<div class="llh-notice llh-notice--success"><?php esc_html_e( 'Thank you! Your boost is being activated — it appears within a minute of payment confirmation.', 'limolisthone' ); ?></div>
		<?php elseif ( isset( $_GET['llh_boost'] ) && 'cancelled' === $_GET['llh_boost'] ) : ?>
			<div class="llh-notice llh-notice--error"><?php esc_html_e( 'Checkout was cancelled — your listing was not boosted.', 'limolisthone' ); ?></div>
		<?php endif; ?>

		<header class="llh-single-head">
			<div>
				<h1><?php the_title(); ?><?php if ( $boosted ) : ?> <span class="llh-badge" style="position:static;"><?php esc_html_e( 'Featured', 'limolisthone' ); ?></span><?php endif; ?></h1>
				<?php if ( $rating['count'] ) : ?>
					<p><?php echo LLH_Reviews::stars_html( $rating['avg'], $rating['count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div>
			<a class="llh-button" href="#quote"><?php esc_html_e( 'Request a quote', 'limolisthone' ); ?></a>
		</header>

		<div class="llh-single-layout">
			<div>
				<?php if ( has_post_thumbnail() || $gallery ) : ?>
					<div class="llh-gallery">
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php echo esc_url( get_the_post_thumbnail_url( $listing_id, 'full' ) ); ?>" target="_blank" rel="noopener">
								<?php the_post_thumbnail( 'large' ); ?>
							</a>
						<?php endif; ?>
						<?php foreach ( $gallery as $image_id ) : ?>
							<a href="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ); ?>" target="_blank" rel="noopener">
								<?php echo wp_get_attachment_image( $image_id, 'llh-card' ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="llh-prose"><?php the_content(); ?></div>

				<?php
				$amenities = get_the_terms( $listing_id, 'amenities' );
				if ( $amenities && ! is_wp_error( $amenities ) ) :
					?>
					<h3><?php esc_html_e( 'Amenities', 'limolisthone' ); ?></h3>
					<div class="llh-card__tags">
						<?php foreach ( $amenities as $amenity ) : ?>
							<span class="llh-chip"><?php echo esc_html( $amenity->name ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				if ( class_exists( 'LLH_Quotes' ) ) {
					LLH_Quotes::render_form( $listing_id );
				}

				comments_template();
				?>
			</div>

			<aside class="llh-facts">
				<h3><?php esc_html_e( 'Company details', 'limolisthone' ); ?></h3>
				<dl>
					<?php
					$facts = array(
						'_llh_phone'        => __( 'Phone', 'limolisthone' ),
						'_llh_email'        => __( 'Email', 'limolisthone' ),
						'_llh_website'      => __( 'Website', 'limolisthone' ),
						'_llh_address'      => __( 'Address', 'limolisthone' ),
						'_llh_fleet_size'   => __( 'Fleet size', 'limolisthone' ),
						'_llh_max_capacity' => __( 'Max passengers', 'limolisthone' ),
						'_llh_price_range'  => __( 'Price range', 'limolisthone' ),
					);
					foreach ( $facts as $key => $label ) :
						$value = get_post_meta( $listing_id, $key, true );
						if ( '' === $value || 0 === $value ) {
							continue;
						}
						?>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd>
							<?php
							if ( '_llh_website' === $key ) {
								printf( '<a href="%1$s" target="_blank" rel="noopener nofollow">%1$s</a>', esc_url( $value ) );
							} elseif ( '_llh_phone' === $key ) {
								printf( '<a href="tel:%s">%s</a>', esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ), esc_html( $value ) );
							} elseif ( '_llh_email' === $key ) {
								printf( '<a href="mailto:%s">%s</a>', esc_attr( $value ), esc_html( $value ) );
							} else {
								echo esc_html( $value );
							}
							?>
						</dd>
					<?php endforeach; ?>

					<?php
					$areas = get_the_term_list( $listing_id, 'service_area', '', ', ' );
					if ( $areas && ! is_wp_error( $areas ) ) :
						?>
						<dt><?php esc_html_e( 'Service areas', 'limolisthone' ); ?></dt>
						<dd><?php echo wp_kses_post( $areas ); ?></dd>
					<?php endif; ?>

					<?php
					$types = get_the_term_list( $listing_id, 'vehicle_type', '', ', ' );
					if ( $types && ! is_wp_error( $types ) ) :
						?>
						<dt><?php esc_html_e( 'Vehicle types', 'limolisthone' ); ?></dt>
						<dd><?php echo wp_kses_post( $types ); ?></dd>
					<?php endif; ?>
				</dl>

				<?php
				if ( class_exists( 'LLH_Stripe' ) ) {
					LLH_Stripe::render_boost_button( $listing_id );
				}
				?>
			</aside>
		</div>
	</div>
	<?php
endwhile;

get_footer();
