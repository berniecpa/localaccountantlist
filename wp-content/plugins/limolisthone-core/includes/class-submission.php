<?php
/**
 * Front-end listing submission: [limo_submit_listing] shortcode form that
 * creates a pending listing for admin moderation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Submission {

	const MAX_PHOTOS = 5;

	public static function init() {
		add_shortcode( 'limo_submit_listing', array( __CLASS__, 'render_form' ) );
		add_action( 'admin_post_llh_submit_listing', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_llh_submit_listing', array( __CLASS__, 'handle' ) );
	}

	public static function render_form() {
		$status = isset( $_GET['llh_submitted'] ) ? sanitize_key( $_GET['llh_submitted'] ) : '';

		ob_start();

		if ( 'ok' === $status ) {
			printf(
				'<div class="llh-notice llh-notice--success">%s</div>',
				esc_html__( 'Thanks! Your listing was submitted and will appear once our team approves it.', 'limolisthone' )
			);
			return ob_get_clean();
		}
		if ( 'error' === $status ) {
			printf(
				'<div class="llh-notice llh-notice--error">%s</div>',
				esc_html__( 'Something was missing or invalid. Please fill in the company name, description, and a valid contact email.', 'limolisthone' )
			);
		}

		$areas    = get_terms( array( 'taxonomy' => 'service_area', 'hide_empty' => false ) );
		$vehicles = get_terms( array( 'taxonomy' => 'vehicle_type', 'hide_empty' => false ) );
		$fields   = LLH_Post_Types::meta_fields();
		?>
		<form class="llh-form llh-submit-form" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="llh_submit_listing" />
			<?php wp_nonce_field( 'llh_submit_listing', 'llh_submit_nonce' ); ?>
			<p class="llh-hp"><label><?php esc_html_e( 'Leave this field empty', 'limolisthone' ); ?><input type="text" name="llh_hp" value="" tabindex="-1" autocomplete="off" /></label></p>

			<p>
				<label for="llh_company"><?php esc_html_e( 'Company name *', 'limolisthone' ); ?></label>
				<input type="text" id="llh_company" name="llh_company" required />
			</p>
			<p>
				<label for="llh_description"><?php esc_html_e( 'Description *', 'limolisthone' ); ?></label>
				<textarea id="llh_description" name="llh_description" rows="6" required></textarea>
			</p>

			<?php foreach ( $fields as $key => $field ) : ?>
				<p>
					<label for="f<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?><?php echo '_llh_email' === $key ? ' *' : ''; ?></label>
					<input type="<?php echo esc_attr( $field[2] ); ?>" id="f<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $key ); ?>" <?php echo '_llh_email' === $key ? 'required' : ''; ?>
						<?php echo 'number' === $field[2] ? 'min="0"' : ''; ?> />
				</p>
			<?php endforeach; ?>

			<?php if ( ! is_wp_error( $areas ) && $areas ) : ?>
				<fieldset class="llh-checkgroup">
					<legend><?php esc_html_e( 'Service areas', 'limolisthone' ); ?></legend>
					<?php foreach ( $areas as $term ) : ?>
						<label><input type="checkbox" name="llh_areas[]" value="<?php echo (int) $term->term_id; ?>" /> <?php echo esc_html( $term->name ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<?php if ( ! is_wp_error( $vehicles ) && $vehicles ) : ?>
				<fieldset class="llh-checkgroup">
					<legend><?php esc_html_e( 'Vehicle types', 'limolisthone' ); ?></legend>
					<?php foreach ( $vehicles as $term ) : ?>
						<label><input type="checkbox" name="llh_vehicles[]" value="<?php echo (int) $term->term_id; ?>" /> <?php echo esc_html( $term->name ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<p>
				<label for="llh_amenities"><?php esc_html_e( 'Amenities (comma separated)', 'limolisthone' ); ?></label>
				<input type="text" id="llh_amenities" name="llh_amenities" placeholder="<?php esc_attr_e( 'Wet bar, WiFi, Red carpet service', 'limolisthone' ); ?>" />
			</p>

			<p>
				<label for="llh_photos"><?php printf( esc_html__( 'Photos (up to %d images)', 'limolisthone' ), (int) self::MAX_PHOTOS ); ?></label>
				<input type="file" id="llh_photos" name="llh_photos[]" accept="image/*" multiple />
			</p>

			<p><button type="submit" class="llh-button"><?php esc_html_e( 'Submit listing for review', 'limolisthone' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$redirect = remove_query_arg( 'llh_submitted', $redirect );

		if ( ! isset( $_POST['llh_submit_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['llh_submit_nonce'] ), 'llh_submit_listing' ) ) {
			wp_safe_redirect( add_query_arg( 'llh_submitted', 'error', $redirect ) );
			exit;
		}

		// Honeypot: bots fill it, humans never see it.
		if ( ! empty( $_POST['llh_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'llh_submitted', 'ok', $redirect ) );
			exit;
		}

		$company     = isset( $_POST['llh_company'] ) ? sanitize_text_field( wp_unslash( $_POST['llh_company'] ) ) : '';
		$description = isset( $_POST['llh_description'] ) ? wp_kses_post( wp_unslash( $_POST['llh_description'] ) ) : '';
		$email       = isset( $_POST['_llh_email'] ) ? sanitize_email( wp_unslash( $_POST['_llh_email'] ) ) : '';

		if ( ! $company || ! $description || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'llh_submitted', 'error', $redirect ) );
			exit;
		}

		$listing_id = wp_insert_post(
			array(
				'post_type'    => 'limo_listing',
				'post_status'  => 'pending',
				'post_title'   => $company,
				'post_content' => $description,
			),
			true
		);

		if ( is_wp_error( $listing_id ) ) {
			wp_safe_redirect( add_query_arg( 'llh_submitted', 'error', $redirect ) );
			exit;
		}

		update_post_meta( $listing_id, '_llh_frontend_submission', 1 );
		update_post_meta( $listing_id, '_llh_boost_active', '0' );

		foreach ( LLH_Post_Types::meta_fields() as $key => $field ) {
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
				update_post_meta( $listing_id, $key, call_user_func( $field[1], wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( ! empty( $_POST['llh_areas'] ) ) {
			wp_set_object_terms( $listing_id, array_map( 'absint', (array) $_POST['llh_areas'] ), 'service_area' );
		}
		if ( ! empty( $_POST['llh_vehicles'] ) ) {
			wp_set_object_terms( $listing_id, array_map( 'absint', (array) $_POST['llh_vehicles'] ), 'vehicle_type' );
		}
		if ( ! empty( $_POST['llh_amenities'] ) ) {
			$amenities = array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_POST['llh_amenities'] ) ) ) );
			if ( $amenities ) {
				wp_set_object_terms( $listing_id, $amenities, 'amenities' );
			}
		}

		self::attach_photos( $listing_id );

		// The pending transition fires the admin notification (see LLH_Admin).
		wp_safe_redirect( add_query_arg( 'llh_submitted', 'ok', $redirect ) );
		exit;
	}

	/**
	 * Sideload uploaded photos as attachments; first image becomes the
	 * featured image, the rest go into the gallery meta.
	 */
	protected static function attach_photos( $listing_id ) {
		if ( empty( $_FILES['llh_photos'] ) || empty( $_FILES['llh_photos']['name'][0] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files   = $_FILES['llh_photos'];
		$count   = min( count( $files['name'] ), self::MAX_PHOTOS );
		$gallery = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
				continue;
			}

			$file = array(
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);

			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
				continue;
			}

			$_FILES['llh_single_photo'] = $file;
			$attachment_id              = media_handle_upload( 'llh_single_photo', $listing_id );
			unset( $_FILES['llh_single_photo'] );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			if ( ! has_post_thumbnail( $listing_id ) ) {
				set_post_thumbnail( $listing_id, $attachment_id );
			} else {
				$gallery[] = $attachment_id;
			}
		}

		if ( $gallery ) {
			update_post_meta( $listing_id, '_llh_gallery', $gallery );
		}
	}
}
