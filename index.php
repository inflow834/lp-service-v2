<?php
/**
 * デフォルトフォールバックテンプレート
 * このテーマは投稿・固定ページを使用しないため、通常表示されることはない想定
 */

get_header();
?>
<main class="lp-fallback">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article>
				<h1><?php the_title(); ?></h1>
				<div><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p>コンテンツが見つかりませんでした。</p>
	<?php endif; ?>
</main>
<?php
get_footer();
