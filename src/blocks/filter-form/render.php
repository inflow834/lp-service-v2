<?php
/**
 * 絞り込みフォーム フロント出力
 *
 * @var array $attributes
 */

$service_id = get_the_ID();
if ( 'service' !== get_post_type( $service_id ) ) {
	return;
}

$heading     = $attributes['heading'] ?? '';
$button_text = $attributes['buttonText'] ?? 'この条件で検索する';
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<?php echo lp_service_render_filter_form( $service_id, $heading, $button_text ); // phpcs:ignore ?>
</div>
