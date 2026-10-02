<?php
/**
 * 商品テーブル(6項目/3列2行) フロント出力
 *
 * @var array $attributes
 */

$items = $attributes['items'] ?? array();
if ( empty( $items ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'lp-product-table' ) ); ?>>
	<div class="lp-product-table__grid">
		<?php foreach ( $items as $item ) : ?>
			<div class="lp-product-table__cell">
				<?php if ( ! empty( $item['label'] ) ) : ?>
					<p class="lp-product-table__label"><?php echo esc_html( $item['label'] ); ?></p>
				<?php endif; ?>
				<div class="lp-product-table__icon lp-product-table__icon--<?php echo esc_attr( $item['icon'] ?? 'double_circle' ); ?>">
					<?php echo lp_service_get_icon_svg( $item['icon'] ?? 'double_circle' ); // phpcs:ignore ?>
				</div>
				<?php if ( ! empty( $item['caption'] ) ) : ?>
					<p class="lp-product-table__caption"><?php echo esc_html( $item['caption'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
