<?php
/**
 * カスタムブロックの登録
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lp_service_register_blocks() {
	$build_dir = LP_SERVICE_DIR . '/build/blocks';
	if ( ! is_dir( $build_dir ) ) {
		return;
	}

	foreach ( glob( $build_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}
}
add_action( 'init', 'lp_service_register_blocks' );

/**
 * ハマったポイント: block.jsonに'version'キーが無いと、ブロックのCSS/JSアセットは
 * WPコアのバージョン（?ver=7.1のような固定値）でエンキューされてしまい、
 * ビルドし直してファイル内容を変えてもブラウザにキャッシュされたまま反映されない
 * （register_block_type()の第2引数に'version'を渡しても、実際にスクリプト/スタイルの
 * ハンドル登録に使われる$metadataには反映されないため効果がない）。
 * 'block_type_metadata'フィルターで直接$metadataに'version'を注入することで、
 * LP_SERVICE_VERSIONを上げるだけで全ブロックのCSS/JSのキャッシュを確実に切れるようにする。
 */
function lp_service_block_type_metadata_version( $metadata ) {
	if ( isset( $metadata['name'] ) && str_starts_with( $metadata['name'], 'lp-service/' ) ) {
		$metadata['version'] = LP_SERVICE_VERSION;
	}
	return $metadata;
}
add_filter( 'block_type_metadata', 'lp_service_block_type_metadata_version' );

/**
 * ブロックインサーターにLP Service専用カテゴリーを追加
 */
function lp_service_block_categories( $categories ) {
	return array_merge(
		array(
			array(
				'slug'  => 'lp-service',
				'title' => 'LP Serviceブロック',
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'lp_service_block_categories' );

/**
 * テキスト装飾（黄色マーカー・赤文字・青文字）
 * CSSはフロントとエディタのキャンバス（iframe）の両方に必要なため enqueue_block_assets で読み込む。
 * 書式の登録JSはエディタ本体にだけ必要（LP・商品の編集画面のみ）。
 */
function lp_service_enqueue_text_formats_style() {
	wp_enqueue_style(
		'lp-service-text-formats',
		LP_SERVICE_URI . '/assets/css/text-formats.css',
		array(),
		LP_SERVICE_VERSION
	);
}
add_action( 'enqueue_block_assets', 'lp_service_enqueue_text_formats_style' );

function lp_service_enqueue_text_formats_script() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'service', 'product' ), true ) ) {
		return;
	}

	wp_enqueue_script(
		'lp-service-text-formats',
		LP_SERVICE_URI . '/assets/js/text-formats.js',
		array( 'wp-rich-text', 'wp-block-editor', 'wp-element' ),
		LP_SERVICE_VERSION,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'lp_service_enqueue_text_formats_script' );

/**
 * 投稿タイプごとに使用可能なブロックを制限
 */
function lp_service_allowed_block_types( $allowed_blocks, $context ) {
	$post_type = $context->post ? $context->post->post_type : '';

	$core_basics = array(
		'core/paragraph',
		'core/heading',
		'core/image',
		'core/list',
		'core/list-item',
		'core/buttons',
		'core/button',
		'core/columns',
		'core/column',
		'core/group',
		'core/spacer',
		'core/separator',
	);

	if ( 'service' === $post_type ) {
		return array_merge(
			$core_basics,
			array(
				'lp-service/free-section',
				'lp-service/comparison-table',
				'lp-service/cta',
				'lp-service/points',
				'lp-service/points-item',
				'lp-service/ranking',
				'lp-service/filter-form',
				'lp-service/simple-banner',
				'lp-service/simple-button',
				'lp-service/simple-description',
				'lp-service/simple-heading',
				'lp-service/micro-copy',
				'lp-service/mark-list',
			)
		);
	}

	if ( 'product' === $post_type ) {
		return array_merge(
			$core_basics,
			array(
				'lp-service/free-section',
				'lp-service/product-table',
				'lp-service/product-list',
				'lp-service/product-review',
				'lp-service/product-cta',
				'lp-service/micro-copy',
				'lp-service/mark-list',
			)
		);
	}

	return $allowed_blocks;
}
add_filter( 'allowed_block_types_all', 'lp_service_allowed_block_types', 10, 2 );
