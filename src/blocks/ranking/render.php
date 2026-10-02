<?php
/**
 * ランキング フロント出力（順位は管理画面での並び順どおり）
 * 商品編集画面に登録した内容（テーブル・リスト・口コミ・CTA等）をすべて展開表示する。
 *
 * @var array $attributes
 */

$title       = $attributes['title'] ?? '';
$product_ids = array_map( 'absint', (array) ( $attributes['productIds'] ?? array() ) );
$product_ids = array_filter( $product_ids );

if ( empty( $product_ids ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'lp-ranking' ) ); ?>>
	<?php if ( $title ) : ?>
		<h2 class="lp-ranking__heading"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="lp-ranking__list">
		<?php
		$rank = 0;
		foreach ( $product_ids as $product_id ) :
			$post = get_post( $product_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			++$rank;
			?>
			<div class="lp-ranking__item">
				<span class="lp-ranking__rank">
					<span class="lp-ranking__rank-number"><?php echo (int) $rank; ?></span>
				</span>
				<div class="lp-ranking__item-body">
					<?php echo lp_service_render_product_full( $product_id ); // phpcs:ignore ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
