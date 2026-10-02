<?php
/**
 * デザイン: スタンダード（LPページ全体のレイアウト）
 *
 * @var array $args { 'header_data' => array }
 */

$header_data = $args['header_data'];

get_template_part( 'template-parts/service/header', null, array( 'header_data' => $header_data ) );
?>
<main class="lp-body">
	<?php the_content(); ?>
</main>
