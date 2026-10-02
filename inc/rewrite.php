<?php
/**
 * LPごとの絞り込みページ /{service-slug}/search/ のリライト設定
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * クエリ変数を追加
 */
function lp_service_query_vars( $vars ) {
	$vars[] = 'lp_service_search';
	return $vars;
}
add_filter( 'query_vars', 'lp_service_query_vars' );

/**
 * service投稿をルート直下URL（例: /lp1/）で解決するリライトルールと、
 * その絞り込みページ（例: /lp1/search/）のリライトルールを手動登録する。
 *
 * 注意: このテーマは「通常のサイト構成を持たない」要件のため、
 * ルート直下の1階層URLはすべてservice投稿として解決される。
 * 固定ページ（page）のルート直下パーマリンクとは共存できない。
 */
function lp_service_add_search_rewrite_rules() {
	add_rewrite_rule(
		'^([^/]+)/search/?$',
		'index.php?service=$matches[1]&lp_service_search=1',
		'top'
	);
	add_rewrite_rule(
		'^([^/]+)/?$',
		'index.php?service=$matches[1]',
		'top'
	);
}
add_action( 'init', 'lp_service_add_search_rewrite_rules', 20 );

/**
 * service投稿のパーマリンクをルート直下URLで生成
 *
 * $leavenameがtrueのとき（管理画面のスラッグ編集欄を生成するget_sample_permalink()経由の呼び出し）は、
 * 実際のスラッグではなく%postname%プレースホルダーを返す必要がある。
 * これを守らないと、WordPressが「編集不可なパーマリンク」と判断し、
 * 投稿編集画面のスラッグ編集ボタンごと表示されなくなる。
 */
function lp_service_post_type_link( $post_link, $post, $leavename = false ) {
	if ( 'service' !== $post->post_type ) {
		return $post_link;
	}

	$slug = $leavename ? '%postname%' : $post->post_name;

	return home_url( user_trailingslashit( $slug ) );
}
add_filter( 'post_type_link', 'lp_service_post_type_link', 10, 3 );

/**
 * lp_service_search=1 のときは専用テンプレートを使う
 */
function lp_service_template_include( $template ) {
	if ( is_singular( 'service' ) && get_query_var( 'lp_service_search' ) ) {
		$custom = locate_template( 'template-parts/service-search.php' );
		if ( $custom ) {
			return $custom;
		}
	}
	return $template;
}
add_filter( 'template_include', 'lp_service_template_include' );
