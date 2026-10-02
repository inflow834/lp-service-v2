<?php
/**
 * LPヘッダーセクション（サイトロゴ／キャッチコピー／バナー）
 *
 * @var array $args { 'header_data' => array }
 */

$header_data = $args['header_data'];
?>
<header class="lp-header">
	<div class="lp-header__inner">
		<div class="lp-header__logo">
			<?php if ( 'image' === $header_data['logo_type'] && $header_data['logo_image'] ) : ?>
				<?php echo wp_get_attachment_image( $header_data['logo_image'], 'medium' ); ?>
			<?php elseif ( $header_data['logo_text'] ) : ?>
				<span class="lp-header__logo-text"><?php echo esc_html( $header_data['logo_text'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $header_data['catch_copy'] ) : ?>
			<p class="lp-header__catch"><?php echo esc_html( $header_data['catch_copy'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $header_data['banner_image'] ) : ?>
		<div class="lp-header__banner">
			<?php echo wp_get_attachment_image( $header_data['banner_image'], 'full' ); ?>
		</div>
	<?php endif; ?>
</header>
