<?php
/**
 * Reviews list + review form for listings (and standard comments elsewhere).
 */

if ( post_password_required() ) {
	return;
}

$is_listing = 'limo_listing' === get_post_type();
?>
<section class="llh-comments" id="comments">
	<?php if ( have_comments() ) : ?>
		<h3>
			<?php
			if ( $is_listing ) {
				/* translators: %d: review count */
				printf( esc_html( _n( '%d review', '%d reviews', (int) get_comments_number(), 'limolisthone' ) ), (int) get_comments_number() );
			} else {
				/* translators: %d: comment count */
				printf( esc_html( _n( '%d comment', '%d comments', (int) get_comments_number(), 'limolisthone' ) ), (int) get_comments_number() );
			}
			?>
		</h3>

		<ol>
			<?php
			wp_list_comments(
				array(
					'style'    => 'ol',
					'callback' => function ( $comment, $args, $depth ) {
						?>
						<li id="comment-<?php comment_ID(); ?>" <?php comment_class( '', $comment ); ?>>
							<div class="llh-comment-meta">
								<strong><?php echo esc_html( get_comment_author( $comment ) ); ?></strong>
								&middot; <?php echo esc_html( get_comment_date( '', $comment ) ); ?>
								<?php if ( '0' === $comment->comment_approved ) : ?>
									&middot; <em><?php esc_html_e( 'Awaiting moderation', 'limolisthone' ); ?></em>
								<?php endif; ?>
							</div>
							<?php comment_text( $comment ); ?>
						<?php // </li> closed by wp_list_comments. ?>
						<?php
					},
				)
			);
			?>
		</ol>

		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'         => $is_listing ? __( 'Write a review', 'limolisthone' ) : __( 'Leave a comment', 'limolisthone' ),
			'label_submit'        => $is_listing ? __( 'Post review', 'limolisthone' ) : __( 'Post comment', 'limolisthone' ),
			'comment_notes_after' => '',
		)
	);
	?>
</section>
