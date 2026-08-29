<?php
/**
 * Quote requests: form rendered on listing detail pages; each lead is emailed
 * to the listing's contact address and stored as a private llh_quote record.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLH_Quotes {

	public static function init() {
		add_action( 'admin_post_llh_quote', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_llh_quote', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Render the quote form for a listing. Called from the theme.
	 */
	public static function render_form( $listing_id ) {
		$status = isset( $_GET['llh_quote'] ) ? sanitize_key( $_GET['llh_quote'] ) : '';
		?>
		<div class="llh-quote-form" id="quote">
			<h3><?php esc_html_e( 'Request a quote', 'limolisthone' ); ?></h3>

			<?php if ( 'ok' === $status ) : ?>
				<div class="llh-notice llh-notice--success"><?php esc_html_e( 'Your request was sent. The company will contact you directly.', 'limolisthone' ); ?></div>
			<?php else : ?>
				<?php if ( 'error' === $status ) : ?>
					<div class="llh-notice llh-notice--error"><?php esc_html_e( 'Please provide your name, a valid email, and a message.', 'limolisthone' ); ?></div>
				<?php endif; ?>

				<form class="llh-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="llh_quote" />
					<input type="hidden" name="llh_listing_id" value="<?php echo (int) $listing_id; ?>" />
					<?php wp_nonce_field( 'llh_quote_' . $listing_id, 'llh_quote_nonce' ); ?>
					<p class="llh-hp"><label><?php esc_html_e( 'Leave this field empty', 'limolisthone' ); ?><input type="text" name="llh_hp" value="" tabindex="-1" autocomplete="off" /></label></p>

					<div class="llh-form-row">
						<p>
							<label for="llh_q_name"><?php esc_html_e( 'Your name *', 'limolisthone' ); ?></label>
							<input type="text" id="llh_q_name" name="llh_q_name" required />
						</p>
						<p>
							<label for="llh_q_email"><?php esc_html_e( 'Your email *', 'limolisthone' ); ?></label>
							<input type="email" id="llh_q_email" name="llh_q_email" required />
						</p>
					</div>
					<div class="llh-form-row">
						<p>
							<label for="llh_q_date"><?php esc_html_e( 'Event date', 'limolisthone' ); ?></label>
							<input type="date" id="llh_q_date" name="llh_q_date" />
						</p>
						<p>
							<label for="llh_q_party"><?php esc_html_e( 'Party size', 'limolisthone' ); ?></label>
							<input type="number" id="llh_q_party" name="llh_q_party" min="1" />
						</p>
					</div>
					<p>
						<label for="llh_q_message"><?php esc_html_e( 'Tell them about your trip *', 'limolisthone' ); ?></label>
						<textarea id="llh_q_message" name="llh_q_message" rows="5" required></textarea>
					</p>
					<p><button type="submit" class="llh-button"><?php esc_html_e( 'Send quote request', 'limolisthone' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle() {
		$listing_id = isset( $_POST['llh_listing_id'] ) ? absint( $_POST['llh_listing_id'] ) : 0;
		$listing    = $listing_id ? get_post( $listing_id ) : null;
		$redirect   = $listing ? get_permalink( $listing ) : home_url( '/' );
		$redirect   = remove_query_arg( 'llh_quote', $redirect );

		$fail = function () use ( $redirect ) {
			wp_safe_redirect( add_query_arg( 'llh_quote', 'error', $redirect ) . '#quote' );
			exit;
		};

		if ( ! $listing || 'limo_listing' !== $listing->post_type ) {
			$fail();
		}
		if ( ! isset( $_POST['llh_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['llh_quote_nonce'] ), 'llh_quote_' . $listing_id ) ) {
			$fail();
		}
		if ( ! empty( $_POST['llh_hp'] ) ) {
			// Honeypot hit: pretend success so bots learn nothing.
			wp_safe_redirect( add_query_arg( 'llh_quote', 'ok', $redirect ) . '#quote' );
			exit;
		}

		$name    = isset( $_POST['llh_q_name'] ) ? sanitize_text_field( wp_unslash( $_POST['llh_q_name'] ) ) : '';
		$email   = isset( $_POST['llh_q_email'] ) ? sanitize_email( wp_unslash( $_POST['llh_q_email'] ) ) : '';
		$date    = isset( $_POST['llh_q_date'] ) ? sanitize_text_field( wp_unslash( $_POST['llh_q_date'] ) ) : '';
		$party   = isset( $_POST['llh_q_party'] ) ? absint( $_POST['llh_q_party'] ) : 0;
		$message = isset( $_POST['llh_q_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['llh_q_message'] ) ) : '';

		if ( ! $name || ! is_email( $email ) || ! $message ) {
			$fail();
		}

		$body = sprintf(
			/* translators: quote request email body */
			__( "New quote request for %1\$s via %2\$s\n\nFrom: %3\$s <%4\$s>\nEvent date: %5\$s\nParty size: %6\$s\n\n%7\$s\n", 'limolisthone' ),
			$listing->post_title,
			get_bloginfo( 'name' ),
			$name,
			$email,
			$date ? $date : __( 'not specified', 'limolisthone' ),
			$party ? $party : __( 'not specified', 'limolisthone' ),
			$message
		);

		// Keep a private record of the lead for the site owner.
		$quote_id = wp_insert_post(
			array(
				'post_type'    => 'llh_quote',
				'post_status'  => 'private',
				'post_title'   => sprintf( '%s → %s', $name, $listing->post_title ),
				'post_content' => $body,
			)
		);
		if ( $quote_id && ! is_wp_error( $quote_id ) ) {
			update_post_meta( $quote_id, '_llh_quote_listing', $listing_id );
			update_post_meta( $quote_id, '_llh_quote_email', $email );
		}

		$to = get_post_meta( $listing_id, '_llh_email', true );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		wp_mail(
			$to,
			sprintf( __( 'New quote request for %s', 'limolisthone' ), $listing->post_title ),
			$body,
			array( 'Reply-To: ' . $name . ' <' . $email . '>' )
		);

		wp_safe_redirect( add_query_arg( 'llh_quote', 'ok', $redirect ) . '#quote' );
		exit;
	}
}
