<?php
/**
 * LPごとの商品絞り込みページ ( /{service-slug}/search/ )
 * フォームはGET送信で自分自身に遷移してくる。$_GETの条件でその場で絞り込んだ結果を表示する。
 */

get_header();

while ( have_posts() ) :
	the_post();

	$service_id = get_the_ID();
	$criteria   = lp_service_get_current_filter_criteria();
	$query      = lp_service_get_products_for_service( $service_id, $criteria );
	?>
	<main class="lp-search-page">
		<div class="lp-search-page__inner">
			<h1 class="lp-search-page__title"><?php the_title(); ?>の商品一覧</h1>

			<?php
			// lp_service_render_filter_form()を直接呼ぶのではなくrender_block()経由にすることで、
			// filter-formブロックに登録されたCSS/JSアセットが自動的に読み込まれるようにする。
			echo render_block(
				array(
					'blockName'    => 'lp-service/filter-form',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			); // phpcs:ignore
			?>

			<div class="lp-search-results">
				<?php if ( $query->have_posts() ) : ?>
					<?php foreach ( $query->posts as $product ) : ?>
						<?php echo lp_service_render_product_full( $product->ID ); // phpcs:ignore ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="lp-search-empty">条件に一致する商品が見つかりませんでした。</p>
				<?php endif; ?>
			</div>
		</div>
	</main>
	<?php
endwhile;

get_footer();
