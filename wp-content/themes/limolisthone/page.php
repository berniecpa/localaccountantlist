<?php
/**
 * Static pages (including the "Get Listed" submission page).
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="llh-container llh-section">
		<h1><?php the_title(); ?></h1>
		<div class="llh-prose" style="max-width:820px;">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
