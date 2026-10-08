<?php
/**
 * LP Service テーマ functions.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// バージョンの唯一の情報源は style.css の `Version:` ヘッダー。
// WordPressの更新チェック（GitHub Releases自動更新含む）はstyle.cssのVersionと比較するため、
// ここに別のバージョン番号を二重管理しない。この定数はブロック・CSS・JSのキャッシュバスターも兼ねる。
// バージョンを上げるときは style.css の Version だけを書き換えればよい。
$lp_service_theme_version = wp_get_theme( get_template() )->get( 'Version' );
define( 'LP_SERVICE_VERSION', $lp_service_theme_version ? $lp_service_theme_version : '1.0.0' );
unset( $lp_service_theme_version );
define( 'LP_SERVICE_DIR', get_template_directory() );
define( 'LP_SERVICE_URI', get_template_directory_uri() );

require LP_SERVICE_DIR . '/inc/post-types.php';
require LP_SERVICE_DIR . '/inc/meta-boxes.php';
require LP_SERVICE_DIR . '/inc/rewrite.php';
require LP_SERVICE_DIR . '/inc/cushion.php';
require LP_SERVICE_DIR . '/inc/icons.php';
require LP_SERVICE_DIR . '/inc/blocks.php';
require LP_SERVICE_DIR . '/inc/rest.php';
require LP_SERVICE_DIR . '/inc/template-functions.php';
require LP_SERVICE_DIR . '/inc/dummy-content.php';
require LP_SERVICE_DIR . '/inc/admin.php';
require LP_SERVICE_DIR . '/inc/onboarding-tour.php';
require LP_SERVICE_DIR . '/inc/updater.php';

/**
 * テーマセットアップ
 */
function lp_service_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor-style.css' );

	// このテーマはLP専用のため、投稿/固定ページのメニューUIから通常の使い方を隠す。
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'lp_service_setup' );

/**
 * フロント用スタイル・スクリプトの読み込み
 */
function lp_service_enqueue_assets() {
	wp_enqueue_style(
		'lp-service-style',
		LP_SERVICE_URI . '/assets/css/style.css',
		array(),
		LP_SERVICE_VERSION
	);

	// スマホ専用デザイン（シンプル・ポップ / シンプル・トラスト）の幅・配色
	if ( is_singular( 'service' ) ) {
		wp_enqueue_style(
			'lp-service-design-mobile',
			LP_SERVICE_URI . '/assets/css/design-mobile.css',
			array( 'lp-service-style' ),
			LP_SERVICE_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'lp_service_enqueue_assets' );

/**
 * 管理画面: 投稿・コメント機能を管理メニューから外し、LP専用管理画面にする
 */
function lp_service_admin_menu_cleanup() {
	remove_menu_page( 'edit.php' ); // 投稿
	remove_menu_page( 'edit-comments.php' ); // コメント
}
add_action( 'admin_menu', 'lp_service_admin_menu_cleanup', 999 );

/**
 * 管理バーの「投稿を追加」等の不要リンクを削除
 */
function lp_service_admin_bar_cleanup( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'new-post' );
	$wp_admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'lp_service_admin_bar_cleanup', 999 );
