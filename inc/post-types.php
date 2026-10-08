<?php
/**
 * カスタム投稿タイプ・タクソノミー登録
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * service: LP本体。URLはプレフィックス無しでルート直下（例: /lp1/）
 */
function lp_service_register_service_cpt() {
	$labels = array(
		'name'               => 'LP（サービス）',
		'singular_name'      => 'LP',
		'add_new'            => '新規LPを追加',
		'add_new_item'       => '新規LPを追加',
		'edit_item'          => 'LPを編集',
		'new_item'           => '新規LP',
		'view_item'          => 'LPを表示',
		'search_items'       => 'LPを検索',
		'not_found'          => 'LPが見つかりません',
		'not_found_in_trash' => 'ゴミ箱にLPはありません',
		'menu_name'          => 'LP管理',
	);

	register_post_type(
		'service',
		array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-align-left',
			'menu_position'      => 4,
			'hierarchical'       => false,
			'has_archive'        => false,
			'exclude_from_search'=> false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
			// URLをルート直下にする（/lp1/ のようにプレフィックス無し）。
			// register_post_type()はrewrite['slug']が空文字だと投稿タイプ名にフォールバックしてしまうため、
			// rewriteは無効化し、リライトルールとpost_type_linkはinc/rewrite.phpで手動生成する。
			'rewrite'            => false,
			'query_var'          => 'service',
			// 新規LP作成時は空の本文で始まる。デザインテンプレート選択時のブロック自動配置は
			// assets/js/design-template-switcher.js（inc/meta-boxes.phpでエンキュー）が担当する。
		)
	);
}
add_action( 'init', 'lp_service_register_service_cpt' );

/**
 * product: LPに紐づく商品。単独のフロントページは持たない（LP内・絞り込みページで表示）
 */
function lp_service_register_product_cpt() {
	$labels = array(
		'name'               => '商品',
		'singular_name'      => '商品',
		'add_new'            => '新規商品を追加',
		'add_new_item'       => '新規商品を追加',
		'edit_item'          => '商品を編集',
		'new_item'           => '新規商品',
		'search_items'       => '商品を検索',
		'not_found'          => '商品が見つかりません',
		'not_found_in_trash' => 'ゴミ箱に商品はありません',
		'menu_name'          => '商品管理',
	);

	register_post_type(
		'product',
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-cart',
			'menu_position'      => 5,
			'hierarchical'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
			'query_var'          => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes' ),
			// 新規作成時にサンプルコンテンツ入りのブロックを自動挿入する（inc/dummy-content.php）。
			'template'           => lp_service_get_product_default_template(),
			'template_lock'      => false,
		)
	);
}
add_action( 'init', 'lp_service_register_product_cpt' );

/**
 * operator_info: LPのフッターからリンクする「運営者情報」ページ。
 * serviceのルート直下キャッチオール（inc/rewrite.php）と衝突しないよう、
 * 通常のプレフィックス付きURL（/operator-info/{slug}/）で登録する。
 */
function lp_service_register_operator_info_cpt() {
	$labels = array(
		'name'               => '運営者情報',
		'singular_name'      => '運営者情報',
		'add_new'            => '新規運営者情報を追加',
		'add_new_item'       => '新規運営者情報を追加',
		'edit_item'          => '運営者情報を編集',
		'new_item'           => '新規運営者情報',
		'view_item'          => '運営者情報を表示',
		'search_items'       => '運営者情報を検索',
		'not_found'          => '運営者情報が見つかりません',
		'not_found_in_trash' => 'ゴミ箱に運営者情報はありません',
		'menu_name'          => '運営者情報',
	);

	register_post_type(
		'operator_info',
		array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-admin-users',
			'menu_position'      => 6,
			'hierarchical'       => false,
			'has_archive'        => false,
			'supports'           => array( 'title', 'editor', 'revisions' ),
			'rewrite'            => array( 'slug' => 'operator-info', 'with_front' => false ),
			'query_var'          => true,
			// 新規作成時に一般的な運営者情報の入力フォーマットを自動挿入する（inc/dummy-content.php）。
			'template'           => lp_service_get_operator_info_default_template(),
			'template_lock'      => false,
		)
	);
}
add_action( 'init', 'lp_service_register_operator_info_cpt' );

/**
 * 投稿メタ（REST公開）の登録
 */
function lp_service_register_meta() {
	register_post_meta(
		'product',
		'_lp_service_id',
		array(
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'product',
		'_lp_catch_copy',
		array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	$service_header_fields = array(
		'_lp_header_logo_type'  => 'string',
		'_lp_header_logo_text'  => 'string',
		'_lp_header_logo_image' => 'integer',
		'_lp_header_catch_copy' => 'string',
		'_lp_header_banner_image' => 'integer',
		'_lp_design_template'   => 'string',
	);

	foreach ( $service_header_fields as $key => $type ) {
		register_post_meta(
			'service',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	register_post_meta(
		'service',
		'_lp_operator_id',
		array(
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'lp_service_register_meta' );

/**
 * 新規テーマ有効化時にリライトルールをフラッシュ
 */
function lp_service_flush_rewrite_on_switch() {
	lp_service_register_service_cpt();
	lp_service_register_product_cpt();
	lp_service_register_operator_info_cpt();
	lp_service_add_search_rewrite_rules();
	lp_service_add_cushion_rewrite_rule();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'lp_service_flush_rewrite_on_switch' );
