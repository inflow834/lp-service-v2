<?php
/**
 * デザイン: シンプル（LPページ全体のレイアウト）
 * ヘッダーセクション（ロゴ・キャッチコピー）は使わず、本文ブロックのみで構成する1カラムのLP。
 *
 * @var array $args { 'header_data' => array }
 */
?>
<main class="lp-body lp-body--simple">
	<?php the_content(); ?>
</main>
