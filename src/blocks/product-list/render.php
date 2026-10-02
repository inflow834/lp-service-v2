<?php
/**
 * 商品リスト フロント出力
 *
 * @var array $attributes
 */

$heading   = $attributes['heading'] ?? '';
$icon_type = $attributes['iconType'] ?? 'check';
$items     = array_filter( (array) ( $attributes['items'] ?? array() ) );

if ( empty( $items ) && empty( $heading ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'lp-product-list' ) ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="lp-product-list__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>
	<?php if ( $items ) : ?>
		<ul class="lp-product-list__items">
			<?php foreach ( $items as $item ) : ?>
				<li class="lp-product-list__item">
					<span class="lp-product-list__icon"><?php echo lp_service_get_icon_svg( $icon_type ); // phpcs:ignore ?></span>
					<span><?php echo esc_html( $item ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
