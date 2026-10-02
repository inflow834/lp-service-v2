<?php
/**
 * 比較テーブル フロント出力（登録商品を横軸に並べる）
 *
 * @var array $attributes
 */

$title       = $attributes['title'] ?? '';
$product_ids = array_map( 'absint', (array) ( $attributes['productIds'] ?? array() ) );
$product_ids = array_filter( $product_ids );

if ( empty( $product_ids ) ) {
	return;
}

$products = array();
foreach ( $product_ids as $product_id ) {
	$post = get_post( $product_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		continue;
	}
	$blocks     = parse_blocks( $post->post_content );
	$products[] = array(
		'id'        => $product_id,
		'title'     => get_the_title( $product_id ),
		'thumbnail' => get_the_post_thumbnail( $product_id, 'large' ),
		'items'     => lp_service_get_product_table_items( $product_id ),
		'cta_block' => lp_service_find_block( $blocks, 'lp-service/product-cta' ),
	);
}

if ( empty( $products ) ) {
	return;
}

$row_count = 0;
foreach ( $products as $product ) {
	$row_count = max( $row_count, count( $product['items'] ) );
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'lp-comparison-table' ) ); ?>>
	<?php if ( $title ) : ?>
		<h2 class="lp-comparison-table__heading"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="lp-comparison-table__scroll">
		<table class="lp-comparison-table__table">
			<colgroup>
				<col class="lp-comparison-table__col-label">
				<?php foreach ( $products as $product ) : ?>
					<col class="lp-comparison-table__col-product">
				<?php endforeach; ?>
			</colgroup>
			<thead>
				<tr>
					<th class="lp-comparison-table__corner"></th>
					<?php foreach ( $products as $product ) : ?>
						<th class="lp-comparison-table__product-head">
							<p class="lp-comparison-table__product-title"><?php echo esc_html( $product['title'] ); ?></p>
							<?php if ( $product['thumbnail'] ) : ?>
								<div class="lp-comparison-table__thumb"><?php echo $product['thumbnail']; // phpcs:ignore ?></div>
							<?php endif; ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php for ( $row = 0; $row < $row_count; $row++ ) : ?>
					<?php
					$row_label = '';
					foreach ( $products as $product ) {
						if ( ! empty( $product['items'][ $row ]['label'] ) ) {
							$row_label = $product['items'][ $row ]['label'];
							break;
						}
					}
					?>
					<tr>
						<th class="lp-comparison-table__row-label"><?php echo esc_html( $row_label ); ?></th>
						<?php foreach ( $products as $product ) : ?>
							<?php $item = $product['items'][ $row ] ?? null; ?>
							<td class="lp-comparison-table__cell">
								<?php if ( $item ) : ?>
									<span class="lp-comparison-table__icon lp-comparison-table__icon--<?php echo esc_attr( $item['icon'] ?? 'double_circle' ); ?>">
										<?php echo lp_service_get_icon_svg( $item['icon'] ?? 'double_circle' ); // phpcs:ignore ?>
									</span>
									<?php if ( ! empty( $item['caption'] ) ) : ?>
										<span class="lp-comparison-table__caption"><?php echo esc_html( $item['caption'] ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endfor; ?>
				<tr class="lp-comparison-table__cta-row">
					<th class="lp-comparison-table__row-label"></th>
					<?php foreach ( $products as $product ) : ?>
						<td class="lp-comparison-table__cell lp-comparison-table__cell--cta">
							<?php if ( $product['cta_block'] ) : ?>
								<?php echo render_block( $product['cta_block'] ); // phpcs:ignore ?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
	</div>
</div>
