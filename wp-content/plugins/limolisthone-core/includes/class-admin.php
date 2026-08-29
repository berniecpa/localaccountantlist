<?php
/**
 * Admin UI: listing meta boxes, photo gallery picker, boost controls,
 * and new-submission notifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_limo_listing', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_limo_listing', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'notify_admin_of_submission' ), 10, 3 );
		add_filter( 'manage_limo_listing_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_limo_listing_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function enqueue( $hook ) {
		global $post_type;
		if ( 'limo_listing' !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'llh-admin',
			LLH_CORE_URL . 'assets/admin.js',
			array( 'jquery' ),
			LLH_CORE_VERSION,
			true
		);
	}

	public static function add_meta_boxes() {
		add_meta_box( 'llh_details', __( 'Listing Details', 'limolisthone' ), array( __CLASS__, 'render_details' ), 'limo_listing', 'normal', 'high' );
		add_meta_box( 'llh_gallery', __( 'Photo Gallery', 'limolisthone' ), array( __CLASS__, 'render_gallery' ), 'limo_listing', 'normal', 'default' );
		add_meta_box( 'llh_boost', __( 'Boosted Placement', 'limolisthone' ), array( __CLASS__, 'render_boost' ), 'limo_listing', 'side', 'default' );
	}

	public static function render_details( $post ) {
		wp_nonce_field( 'llh_save_listing', 'llh_listing_nonce' );
		echo '<table class="form-table">';
		foreach ( LLH_Post_Types::meta_fields() as $key => $field ) {
			list( $label, , $type ) = $field;
			$value = get_post_meta( $post->ID, $key, true );
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="regular-text" %5$s /></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( $type ),
				esc_attr( $value ),
				'number' === $type ? 'min="0"' : ''
			);
		}
		echo '</table>';
	}

	public static function render_gallery( $post ) {
		$ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_llh_gallery', true ) ) );
		echo '<div id="llh-gallery-preview">';
		foreach ( $ids as $id ) {
			echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'margin:4px;' ) );
		}
		echo '</div>';
		printf(
			'<input type="hidden" id="llh_gallery" name="llh_gallery" value="%s" />',
			esc_attr( implode( ',', $ids ) )
		);
		printf(
			'<p><button type="button" class="button" id="llh-gallery-select">%s</button> <button type="button" class="button" id="llh-gallery-clear">%s</button></p>',
			esc_html__( 'Select images', 'limolisthone' ),
			esc_html__( 'Clear', 'limolisthone' )
		);
	}

	public static function render_boost( $post ) {
		$active  = llh_is_boosted( $post->ID );
		$expires = (int) get_post_meta( $post->ID, '_llh_boost_expires', true );
		$sub     = get_post_meta( $post->ID, '_llh_stripe_subscription', true );

		printf(
			'<p><label><input type="checkbox" name="llh_boost_active" value="1" %s /> %s</label></p>',
			checked( $active, true, false ),
			esc_html__( 'Boosted (appears first in results)', 'limolisthone' )
		);
		printf(
			'<p><label for="llh_boost_expires">%s</label><br /><input type="date" id="llh_boost_expires" name="llh_boost_expires" value="%s" /></p>',
			esc_html__( 'Boost expires (empty = never)', 'limolisthone' ),
			$expires ? esc_attr( gmdate( 'Y-m-d', $expires ) ) : ''
		);
		if ( $sub ) {
			printf(
				'<p class="description">%s <code>%s</code></p>',
				esc_html__( 'Stripe subscription:', 'limolisthone' ),
				esc_html( $sub )
			);
		} else {
			printf( '<p class="description">%s</p>', esc_html__( 'No Stripe subscription — manual boost only.', 'limolisthone' ) );
		}
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['llh_listing_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['llh_listing_nonce'] ), 'llh_save_listing' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( LLH_Post_Types::meta_fields() as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				$sanitize = $field[1];
				update_post_meta( $post_id, $key, call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( isset( $_POST['llh_gallery'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['llh_gallery'] ) ) ) ) );
			update_post_meta( $post_id, '_llh_gallery', $ids );
		}

		if ( ! empty( $_POST['llh_boost_active'] ) ) {
			$expires = 0;
			if ( ! empty( $_POST['llh_boost_expires'] ) ) {
				$expires = strtotime( sanitize_text_field( wp_unslash( $_POST['llh_boost_expires'] ) ) . ' 23:59:59 UTC' );
				$expires = $expires ? $expires : 0;
			}
			llh_set_boost( $post_id, $expires );
		} else {
			llh_clear_boost( $post_id );
		}
	}

	/**
	 * Email the site admin when a front-end submission lands in the pending queue.
	 */
	public static function notify_admin_of_submission( $new_status, $old_status, $post ) {
		if ( 'limo_listing' !== $post->post_type || 'pending' !== $new_status || 'pending' === $old_status ) {
			return;
		}
		// Only submissions from the public form, not drafts an admin marked pending.
		if ( ! get_post_meta( $post->ID, '_llh_frontend_submission', true ) ) {
			return;
		}
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( __( '[%s] New listing submission: %s', 'limolisthone' ), get_bloginfo( 'name' ), $post->post_title ),
			sprintf(
				/* translators: 1: listing title, 2: review URL */
				__( "A new listing \"%1\$s\" was submitted and is awaiting review.\n\nReview it here: %2\$s\n", 'limolisthone' ),
				$post->post_title,
				admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
			)
		);
	}

	public static function columns( $columns ) {
		$columns['llh_boost']  = __( 'Boosted', 'limolisthone' );
		$columns['llh_rating'] = __( 'Rating', 'limolisthone' );
		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'llh_boost' === $column ) {
			echo llh_is_boosted( $post_id ) ? '<span style="color:#b8860b;font-weight:600;">&#9733; ' . esc_html__( 'Boosted', 'limolisthone' ) . '</span>' : '&mdash;';
		}
		if ( 'llh_rating' === $column ) {
			$count = (int) get_post_meta( $post_id, '_llh_rating_count', true );
			if ( $count ) {
				printf( '%s (%d)', esc_html( get_post_meta( $post_id, '_llh_rating_avg', true ) ), (int) $count );
			} else {
				echo '&mdash;';
			}
		}
	}
}
