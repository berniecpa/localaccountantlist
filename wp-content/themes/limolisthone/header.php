<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="llh-header">
	<div class="llh-header__inner">
		<a class="llh-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			$name = get_bloginfo( 'name' );
			if ( $name ) {
				echo wp_kses_post( preg_replace( '/limo/i', '<strong>$0</strong>', esc_html( $name ), 1 ) );
			} else {
				echo '<strong>Limo</strong>ListHone';
			}
			?>
		</a>

		<button class="llh-nav-toggle" aria-expanded="false" aria-controls="llh-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'limolisthone' ); ?></span>&#9776;
		</button>

		<nav id="llh-nav" class="llh-nav" aria-label="<?php esc_attr_e( 'Primary', 'limolisthone' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'depth'          => 1,
					)
				);
			} else {
				llh_default_menu();
			}
			?>
		</nav>
	</div>
</header>
