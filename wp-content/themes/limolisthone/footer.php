<footer class="llh-footer">
	<div class="llh-container llh-footer__inner">
		<div>
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &middot;
			<?php esc_html_e( 'The luxury ground transportation directory.', 'limolisthone' ); ?>
		</div>
		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'depth'          => 1,
				)
			);
		} else {
			llh_default_menu();
		}
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
