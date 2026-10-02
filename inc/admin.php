<?php
/**
 * 管理画面の一覧表示まわりの調整
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 商品一覧に「所属LP」列を追加
 */
function lp_service_product_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['lp_service'] = '所属LP';
		}
	}
	return $new;
}
add_filter( 'manage_product_posts_columns', 'lp_service_product_columns' );

function lp_service_product_column_content( $column, $post_id ) {
	if ( 'lp_service' === $column ) {
		$service_id = (int) get_post_meta( $post_id, '_lp_service_id', true );
		if ( $service_id && get_post( $service_id ) ) {
			echo esc_html( get_the_title( $service_id ) );
		} else {
			echo '<span style="color:#d63638;">未設定</span>';
		}
	}
}
add_action( 'manage_product_posts_custom_column', 'lp_service_product_column_content', 10, 2 );

/**
 * service一覧に絞り込みページへのリンクを追加
 */
function lp_service_service_columns( $columns ) {
	$columns['lp_search_page'] = '絞り込みページ';
	return $columns;
}
add_filter( 'manage_service_posts_columns', 'lp_service_service_columns' );

function lp_service_service_column_content( $column, $post_id ) {
	if ( 'lp_search_page' === $column ) {
		$url = trailingslashit( get_permalink( $post_id ) ) . 'search/';
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
	}
}
add_action( 'manage_service_posts_custom_column', 'lp_service_service_column_content', 10, 2 );

/**
 * パーマリンク未更新による404を防ぐための管理画面通知
 */
function lp_service_rewrite_notice() {
	if ( get_option( 'lp_service_rewrite_flushed' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'lp_service_rewrite_flushed', 1 );
}
add_action( 'admin_init', 'lp_service_rewrite_notice' );
