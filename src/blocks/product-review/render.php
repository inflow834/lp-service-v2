<?php
/**
 * 口コミ フロント出力
 *
 * @var array $attributes
 */

$avatar_id = (int) ( $attributes['avatarId'] ?? 0 );
$title     = $attributes['title'] ?? '';
$rating    = (int) ( $attributes['rating'] ?? 5 );
$comment   = $attributes['comment'] ?? '';

if ( ! $title && ! $comment ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'lp-product-review' ) ); ?>>
	<div class="lp-product-review__head">
		<?php if ( $avatar_id ) : ?>
			<span class="lp-product-review__avatar">
				<?php echo wp_get_attachment_image( $avatar_id, 'thumbnail' ); ?>
			</span>
		<?php endif; ?>
		<div class="lp-product-review__meta">
			<?php if ( $title ) : ?>
				<p class="lp-product-review__title"><?php echo esc_html( $title ); ?></p>
			<?php endif; ?>
			<?php echo lp_service_render_star_rating( $rating ); // phpcs:ignore ?>
		</div>
	</div>
	<?php if ( $comment ) : ?>
		<p class="lp-product-review__comment"><?php echo esc_html( $comment ); ?></p>
	<?php endif; ?>
</div>
