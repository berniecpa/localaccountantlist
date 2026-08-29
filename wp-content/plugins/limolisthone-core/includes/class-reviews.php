<?php
/**
 * Star reviews built on native comments: a 1-5 rating field on listing
 * comment forms, stored as comment meta, with the average cached in post meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Reviews {

	public static function init() {
		add_filter( 'comment_form_field_comment', array( __CLASS__, 'add_rating_field' ) );
		add_filter( 'preprocess_comment', array( __CLASS__, 'require_rating' ) );
		add_action( 'comment_post', array( __CLASS__, 'save_rating' ), 10, 3 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'maybe_recount_on_insert' ), 10, 2 );
		add_action( 'transition_comment_status', array( __CLASS__, 'recount_on_transition' ), 10, 3 );
		add_filter( 'comment_text', array( __CLASS__, 'prepend_stars_to_comment' ), 10, 2 );
	}

	protected static function is_listing_comment_form() {
		return is_singular( 'limo_listing' );
	}

	public static function add_rating_field( $comment_field ) {
		if ( ! self::is_listing_comment_form() ) {
			return $comment_field;
		}

		$stars = '<p class="llh-rating-field"><label for="llh_rating">' . esc_html__( 'Your rating *', 'limolisthone' ) . '</label> ';
		$stars .= '<span class="llh-star-input">';
		for ( $i = 5; $i >= 1; $i-- ) {
			$stars .= sprintf(
				'<input type="radio" id="llh_rating_%1$d" name="llh_rating" value="%1$d" required /><label for="llh_rating_%1$d" title="%2$s">&#9733;</label>',
				$i,
				/* translators: %d: star rating */
				esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'limolisthone' ), $i ) )
			);
		}
		$stars .= '</span></p>';

		return $stars . $comment_field;
	}

	/**
	 * Reject listing reviews that arrive without a valid rating.
	 */
	public static function require_rating( $commentdata ) {
		if ( empty( $commentdata['comment_post_ID'] ) || 'limo_listing' !== get_post_type( (int) $commentdata['comment_post_ID'] ) ) {
			return $commentdata;
		}
		// Only top-level visitor reviews need a rating; replies and pingbacks pass through.
		if ( ! empty( $commentdata['comment_parent'] ) || ! empty( $commentdata['comment_type'] ) && 'comment' !== $commentdata['comment_type'] ) {
			return $commentdata;
		}

		$rating = isset( $_POST['llh_rating'] ) ? absint( $_POST['llh_rating'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			wp_die(
				esc_html__( 'Please choose a star rating for your review.', 'limolisthone' ),
				esc_html__( 'Rating required', 'limolisthone' ),
				array( 'back_link' => true )
			);
		}

		return $commentdata;
	}

	public static function save_rating( $comment_id, $approved, $commentdata ) {
		if ( empty( $commentdata['comment_post_ID'] ) || 'limo_listing' !== get_post_type( (int) $commentdata['comment_post_ID'] ) ) {
			return;
		}
		$rating = isset( $_POST['llh_rating'] ) ? absint( $_POST['llh_rating'] ) : 0;
		if ( $rating >= 1 && $rating <= 5 ) {
			update_comment_meta( $comment_id, 'llh_rating', $rating );
		}
	}

	public static function maybe_recount_on_insert( $comment_id, $comment ) {
		if ( '1' === (string) $comment->comment_approved && 'limo_listing' === get_post_type( (int) $comment->comment_post_ID ) ) {
			self::recount( (int) $comment->comment_post_ID );
		}
	}

	public static function recount_on_transition( $new_status, $old_status, $comment ) {
		if ( 'limo_listing' === get_post_type( (int) $comment->comment_post_ID ) ) {
			self::recount( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Recompute and cache the average rating and review count for a listing.
	 */
	public static function recount( $listing_id ) {
		$comments = get_comments(
			array(
				'post_id' => $listing_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'parent'  => 0,
			)
		);

		$sum   = 0;
		$count = 0;
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'llh_rating', true );
			if ( $rating >= 1 && $rating <= 5 ) {
				$sum += $rating;
				$count++;
			}
		}

		if ( $count ) {
			update_post_meta( $listing_id, '_llh_rating_avg', round( $sum / $count, 1 ) );
			update_post_meta( $listing_id, '_llh_rating_count', $count );
		} else {
			delete_post_meta( $listing_id, '_llh_rating_avg' );
			delete_post_meta( $listing_id, '_llh_rating_count' );
		}
	}

	/**
	 * Cached aggregate for a listing: array( avg, count ).
	 */
	public static function rating( $listing_id ) {
		return array(
			'avg'   => (float) get_post_meta( $listing_id, '_llh_rating_avg', true ),
			'count' => (int) get_post_meta( $listing_id, '_llh_rating_count', true ),
		);
	}

	/**
	 * Render a row of stars for a rating value. Escaped, ready to echo.
	 */
	public static function stars_html( $rating, $count = null ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		$full   = (int) floor( $rating + 0.5 );

		$html = '<span class="llh-stars" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'limolisthone' ), $rating ) ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= '<span class="' . ( $i <= $full ? 'llh-star llh-star--full' : 'llh-star' ) . '">&#9733;</span>';
		}
		$html .= '</span>';

		if ( null !== $count ) {
			$html .= ' <span class="llh-stars-count">(' . (int) $count . ')</span>';
		}

		return $html;
	}

	public static function prepend_stars_to_comment( $text, $comment = null ) {
		if ( ! $comment || 'limo_listing' !== get_post_type( (int) $comment->comment_post_ID ) ) {
			return $text;
		}
		$rating = (int) get_comment_meta( $comment->comment_ID, 'llh_rating', true );
		if ( $rating < 1 ) {
			return $text;
		}
		return '<p class="llh-comment-stars">' . self::stars_html( $rating ) . '</p>' . $text;
	}
}
