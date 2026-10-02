<?php
/**
 * 共通SVGアイコン（比較テーブルの評価アイコン／リストアイコン／口コミの星）
 * すべて currentColor を使用し、CSSで色を制御する。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lp_service_get_icon_svg( $key ) {
	$icons = array(
		'double_circle' => '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="4"/><circle cx="24" cy="24" r="11" fill="none" stroke="currentColor" stroke-width="4"/></svg>',
		'circle'        => '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="4"/></svg>',
		'cross'         => '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><line x1="10" y1="10" x2="38" y2="38" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><line x1="38" y1="10" x2="10" y2="38" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>',
		'check'         => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 12.5l5 5L20 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'arrow'         => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'dot'           => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="5" fill="currentColor"/></svg>',
		'star_filled'   => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2.5l2.9 6.2 6.7.7-5 4.7 1.4 6.6L12 17.6l-6 3.1 1.4-6.6-5-4.7 6.7-.7z" fill="currentColor"/></svg>',
		'star_empty'    => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2.5l2.9 6.2 6.7.7-5 4.7 1.4 6.6L12 17.6l-6 3.1 1.4-6.6-5-4.7 6.7-.7z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
	);

	return $icons[ $key ] ?? '';
}

/**
 * 比較テーブル用アイコンの選択肢（テーブルブロックの管理画面・保存値の両方で使用）
 */
function lp_service_table_icon_choices() {
	return array(
		'double_circle' => '二重丸',
		'circle'        => '一重丸',
		'cross'         => 'バツ',
	);
}

/**
 * リストブロック用アイコンの選択肢
 */
function lp_service_list_icon_choices() {
	return array(
		'check' => 'チェックマーク',
		'arrow' => '矢印',
		'dot'   => 'ドット',
	);
}
