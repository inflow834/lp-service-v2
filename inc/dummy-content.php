<?php
/**
 * 新規投稿作成時のデフォルトブロック・サンプルコンテンツ・ダミー画像
 *
 * 本文のサンプルブロックはregister_post_type()の'template'引数で登録する
 * （ブロックエディタが空の新規投稿を開いた時にクライアント側で自動挿入する、WordPress標準の仕組み）。
 *
 * ハマったポイント: 当初はsave_post_{post_type}フックで本文を直接書き込む方式を試したが、
 * ブロックエディタは自動下書き作成時にREST経由で取得した「まだフックが反映される前」の
 * 空の内容をローカルのstateに読み込んでしまい、DB上には内容が保存されているのに
 * エディタ画面には反映されない（0ブロックのまま）という不具合が発生した。
 * 'template'引数はDBの内容取得とは別経路（エディタ初期化時にクライアント側で合成）で
 * 適用されるため、このレースコンディションが起きない。
 *
 * アイキャッチのダミー画像は本文とは別物（post_content外のメタ）で'template'引数では
 * 設定できないため、こちらは引き続きsave_post_productフックで一度だけ設定する
 * （REST応答のfeatured_mediaフィールドは実測でこの方式でも正しく反映されることを確認済み）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * サンプル画像を一度だけメディアライブラリへ登録し、以後は同じ添付ファイルIDを使い回す
 */
function lp_service_get_dummy_image_id() {
	$attachment_id = (int) get_option( 'lp_service_dummy_image_id' );

	if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
		return $attachment_id;
	}

	$source_path = LP_SERVICE_DIR . '/assets/images/dummy-placeholder.jpg';
	if ( ! file_exists( $source_path ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload_dir = wp_upload_dir();
	$filename   = wp_unique_filename( $upload_dir['path'], 'lp-service-sample.jpg' );
	$new_file   = trailingslashit( $upload_dir['path'] ) . $filename;

	if ( ! copy( $source_path, $new_file ) ) {
		return 0;
	}

	$filetype      = wp_check_filetype( $filename, null );
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => 'サンプル画像',
			'post_status'    => 'inherit',
		),
		$new_file
	);

	if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
		return 0;
	}

	$attachment_data = wp_generate_attachment_metadata( $attachment_id, $new_file );
	wp_update_attachment_metadata( $attachment_id, $attachment_data );

	update_option( 'lp_service_dummy_image_id', $attachment_id );

	return $attachment_id;
}

/**
 * 商品の新規作成時にブロックエディタが自動挿入するテンプレート構成（サンプルコンテンツ入り）
 * register_post_type()の'template'引数にそのまま渡す形式 array( array( blockName, attrs ), ... )
 */
function lp_service_get_product_default_template() {
	$dummy_id   = lp_service_get_dummy_image_id();
	$avatar_url = $dummy_id ? wp_get_attachment_image_url( $dummy_id, 'thumbnail' ) : '';

	return array(
		array(
			'lp-service/product-table',
			array(
				'items' => array(
					array(
						'label'   => '手数料',
						'icon'    => 'double_circle',
						'caption' => '無料',
					),
					array(
						'label'   => '出張査定',
						'icon'    => 'double_circle',
						'caption' => '全国対応',
					),
					array(
						'label'   => '査定スピード',
						'icon'    => 'double_circle',
						'caption' => '最短即日',
					),
					array(
						'label'   => 'キャンセル料',
						'icon'    => 'double_circle',
						'caption' => '無料',
					),
					array(
						'label'   => '対応エリア',
						'icon'    => 'circle',
						'caption' => '一部地域を除く全国',
					),
					array(
						'label'   => '保証',
						'icon'    => 'circle',
						'caption' => '30日間返品保証',
					),
				),
			),
		),
		array(
			'lp-service/product-list',
			array(
				'heading'  => '選ばれる理由',
				'iconType' => 'check',
				'items'    => array( '創業20年以上の実績', '査定士全員が有資格者', '即日入金に対応' ),
			),
		),
		array(
			'lp-service/product-review',
			array(
				'avatarId'  => $dummy_id,
				'avatarUrl' => $avatar_url,
				'title'     => '40代 女性',
				'rating'    => 4,
				'comment'   => '丁寧に説明してもらえて安心して査定をお願いできました。金額にも満足しています。',
			),
		),
		array(
			'lp-service/product-cta',
			array(
				'text'      => '詳しくはこちら',
				'url'       => '',
				'color'     => '#d63638',
				'textColor' => '#ffffff',
			),
		),
	);
}

/**
 * 商品を新規作成した直後（自動下書き作成時点）に、アイキャッチのダミー画像を一度だけ設定する。
 * （本文のサンプルブロックはregister_post_type()の'template'引数で別途処理）
 */
function lp_service_apply_product_defaults( $post_id, $post, $update ) {
	if ( 'product' !== $post->post_type ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_meta( $post_id, '_lp_product_defaults_applied', true ) ) {
		return;
	}

	update_post_meta( $post_id, '_lp_product_defaults_applied', 1 );

	$dummy_id = lp_service_get_dummy_image_id();
	if ( $dummy_id && ! has_post_thumbnail( $post_id ) ) {
		set_post_thumbnail( $post_id, $dummy_id );
	}
}
add_action( 'save_post_product', 'lp_service_apply_product_defaults', 10, 3 );

/**
 * 運営者情報の新規作成時にブロックエディタが自動挿入するテンプレート構成
 * （一般的な入力フォーマット＋サンプル文言）
 */
function lp_service_get_operator_info_default_template() {
	$fields = array(
		'運営会社名'     => '株式会社サンプル',
		'代表者名'       => '山田 太郎',
		'所在地'         => '東京都渋谷区〇〇1-2-3',
		'電話番号'       => '03-1234-5678',
		'メールアドレス' => 'info@example.com',
		'事業内容'       => '買取サービスの運営',
		'許可・登録番号' => '古物商許可番号　〇〇公安委員会　第000000000000号',
	);

	$template = array();
	foreach ( $fields as $label => $value ) {
		$template[] = array(
			'core/paragraph',
			array( 'content' => '<strong>' . esc_html( $label ) . '：</strong>' . esc_html( $value ) ),
		);
	}

	return $template;
}
