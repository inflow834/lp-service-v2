<?php
/**
 * REST API拡張: product を service_id で絞り込めるようにする（wp/v2/product エンドポイント用）
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lp_service_rest_product_collection_params( $params ) {
	$params['service_id'] = array(
		'description' => '所属LP（service投稿ID）で絞り込む',
		'type'        => 'integer',
	);
	return $params;
}
add_filter( 'rest_product_collection_params', 'lp_service_rest_product_collection_params' );

function lp_service_rest_product_query( $args, $request ) {
	$service_id = $request->get_param( 'service_id' );
	if ( $service_id ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_lp_service_id',
				'value' => absint( $service_id ),
			),
		);
	}
	return $args;
}
add_filter( 'rest_product_query', 'lp_service_rest_product_query', 10, 2 );
