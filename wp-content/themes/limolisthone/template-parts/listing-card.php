<?php
/**
 * One listing card, used on the front page and archive grids.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$listing_id = get_the_ID();
$boosted    = function_exists( 'llh_is_boosted' ) && llh_is_boosted( $listing_id );
$rating     = class_exists( 'LLH_Reviews' ) ? LLH_Reviews::rating( $listing_id ) : array( 'avg' => 0, 'count' => 0 );
?>
<article <?php post_class( 'llh-card' . ( $boosted ? ' llh-card--boosted' : '' ) ); ?>>
	<a class="llh-card__media" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'llh-card' ); ?>
		<?php else : ?>
			<span class="llh-noimg" aria-hidden="true">&#128663;</span>
		<?php endif; ?>
		<?php if ( $boosted ) : ?>
			<span class="llh-badge"><?php esc_html_e( 'Featured', 'limolisthone' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="llh-card__body">
		<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

		<?php if ( $rating['count'] ) : ?>
			<div><?php echo LLH_Reviews::stars_html( $rating['avg'], $rating['count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<?php llh_card_meta( $listing_id ); ?>

		<?php
		$types = get_the_terms( $listing_id, 'vehicle_type' );
		if ( $types && ! is_wp_error( $types ) ) :
			?>
			<div class="llh-card__tags">
				<?php foreach ( array_slice( $types, 0, 3 ) as $type ) : ?>
					<span class="llh-chip"><?php echo esc_html( $type->name ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
