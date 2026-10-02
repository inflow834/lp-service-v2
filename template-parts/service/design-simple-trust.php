<?php
/**
 * デザイン: シンプル・トラスト（スマホ専用、LPページ全体のレイアウト）
 * ブロック構成は「シンプル」と同じで、見た目（紺×白×ゴールドの落ち着いた配色）だけを変えたもの。
 * 幅・配色は assets/css/design-mobile.css（<body>の lp-design-simple-trust クラス起点）で指定。
 *
 * @var array $args { 'header_data' => array }
 */
?>
<main class="lp-body lp-body--simple lp-body--mobile">
	<?php the_content(); ?>
</main>
