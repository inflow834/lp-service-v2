<?php
/**
 * 運営者情報 シングルテンプレート
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main class="lp-operator-info">
		<div class="lp-operator-info__inner">
			<h1 class="lp-operator-info__title"><?php the_title(); ?></h1>
			<div class="lp-operator-info__content">
				<?php the_content(); ?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
