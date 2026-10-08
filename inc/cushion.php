<?php
/**
 * クッションページ（コンバージョンタグを読み込んでから本来のリンク先へ移動する中継ページ）
 *
 * - URL: /go/{投稿ID}/{ボタン番号}/
 *   投稿IDはボタンが置かれている商品またはLP、ボタン番号はその投稿内でボタンごとに自動で振られる番号（属性 buttonNo）
 * - 対象ブロック: 商品CTAボタン / CTAセクション / シンプルボタン（属性 cushion がtrueのものだけ）
 * - コンバージョンタグは「コンバージョンタグ」管理画面（投稿タイプ lp_cv_tag）で複数登録し、
 *   ボタンごとに選択する。個別タグ（属性 cvTagCustom）が入力されていれば、選択したタグの代わりにそちらを出す
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * クッションページを使えるブロックと、リンク先URLを持つ属性名
 */
function lp_service_cushion_blocks() {
	return array(
		'lp-service/product-cta'   => 'url',
		'lp-service/cta'           => 'buttonUrl',
		'lp-service/simple-button' => 'url',
	);
}

/**
 * コンバージョンタグの投稿タイプ（管理者のみ扱える）
 */
function lp_service_register_cv_tag_cpt() {
	$cap = 'manage_options';

	register_post_type(
		'lp_cv_tag',
		array(
			'labels'          => array(
				'name'               => 'タグ',
				'singular_name'      => 'タグ',
				'menu_name'          => 'タグ',
				'all_items'          => 'タグ一覧',
				'add_new'            => '新規追加',
				'add_new_item'       => 'タグを追加',
				'edit_item'          => 'タグを編集',
				'new_item'           => '新しいタグ',
				'search_items'       => 'タグを検索',
				'not_found'          => 'タグがありません',
				'not_found_in_trash' => 'ゴミ箱にタグはありません',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 7,
			'menu_icon'       => 'dashicons-chart-line',
			'supports'        => array( 'title' ),
			'map_meta_cap'    => false,
			'capabilities'    => array(
				'edit_post'              => $cap,
				'read_post'              => $cap,
				'delete_post'            => $cap,
				'edit_posts'             => $cap,
				'edit_others_posts'      => $cap,
				'edit_published_posts'   => $cap,
				'publish_posts'          => $cap,
				'read_private_posts'     => $cap,
				'delete_posts'           => $cap,
				'delete_others_posts'    => $cap,
				'delete_published_posts' => $cap,
				'create_posts'           => $cap,
			),
		)
	);
}
add_action( 'init', 'lp_service_register_cv_tag_cpt' );

/**
 * タグコードの入力欄
 */
function lp_service_add_cv_tag_meta_box() {
	add_meta_box( 'lp_cv_tag_code', 'タグコード', 'lp_service_render_cv_tag_meta_box', 'lp_cv_tag', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'lp_service_add_cv_tag_meta_box' );

function lp_service_render_cv_tag_meta_box( $post ) {
	wp_nonce_field( 'lp_cv_tag_save', 'lp_cv_tag_nonce' );
	$code = get_post_meta( $post->ID, '_lp_cv_tag_code', true );
	?>
	<p>ASPや広告媒体から発行されたタグ（&lt;script&gt;や&lt;img&gt;など）をそのまま貼り付けてください。クッションページの中で読み込まれます。</p>
	<textarea name="lp_cv_tag_code" rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $code ); ?></textarea>
	<p class="description">タイトルはボタン側でタグを選ぶときの名前になります（例: A8.net 楽器の買取屋さん）。</p>
	<?php
}

function lp_service_save_cv_tag( $post_id ) {
	if ( ! isset( $_POST['lp_cv_tag_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['lp_cv_tag_nonce'] ), 'lp_cv_tag_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// タグコードはスクリプトを含むため、無加工のHTMLを扱える権限のあるユーザーだけが保存できる
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'unfiltered_html' ) ) {
		return;
	}
	$code = isset( $_POST['lp_cv_tag_code'] ) ? wp_unslash( $_POST['lp_cv_tag_code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	update_post_meta( $post_id, '_lp_cv_tag_code', $code );
}
add_action( 'save_post_lp_cv_tag', 'lp_service_save_cv_tag' );

/**
 * 一覧画面にタグIDを表示
 */
function lp_service_cv_tag_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['lp_cv_tag_id'] = 'タグID';
		}
	}
	return $new;
}
add_filter( 'manage_lp_cv_tag_posts_columns', 'lp_service_cv_tag_columns' );

function lp_service_cv_tag_column_content( $column, $post_id ) {
	if ( 'lp_cv_tag_id' === $column ) {
		echo (int) $post_id;
	}
}
add_action( 'manage_lp_cv_tag_posts_custom_column', 'lp_service_cv_tag_column_content', 10, 2 );

/**
 * ブロックエディタ（LP・商品）にタグ一覧などを渡す
 */
function lp_service_enqueue_cushion_editor_data() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'service', 'product' ), true ) ) {
		return;
	}

	$tags = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'lp_cv_tag',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	) as $tag ) {
		$tags[] = array(
			'id'    => $tag->ID,
			'title' => get_the_title( $tag ) ? get_the_title( $tag ) : '（タイトルなし）',
		);
	}

	$data = array(
		'tags'              => $tags,
		'canUnfilteredHtml' => current_user_can( 'unfiltered_html' ),
		'canManageTags'     => current_user_can( 'manage_options' ),
		'tagsAdminUrl'      => admin_url( 'edit.php?post_type=lp_cv_tag' ),
		'cushionBaseUrl'    => home_url( '/go/' ),
	);

	wp_register_script( 'lp-service-cushion-data', false, array(), LP_SERVICE_VERSION, false );
	wp_enqueue_script( 'lp-service-cushion-data' );
	wp_add_inline_script( 'lp-service-cushion-data', 'window.lpServiceCushion = ' . wp_json_encode( $data ) . ';' );
}
add_action( 'enqueue_block_editor_assets', 'lp_service_enqueue_cushion_editor_data' );

/**
 * クッションページのURL
 */
function lp_service_get_cushion_url( $post_id, $button_no ) {
	return home_url( user_trailingslashit( 'go/' . (int) $post_id . '/' . (int) $button_no ) );
}

/**
 * リライトルール /go/{投稿ID}/{ボタン番号}/
 * 2階層以上のURLなので、service用のルート直下ルール（^([^/]+)/?$）とは衝突しない
 */
function lp_service_cushion_query_vars( $vars ) {
	$vars[] = 'lp_cushion_post';
	$vars[] = 'lp_cushion_button';
	return $vars;
}
add_filter( 'query_vars', 'lp_service_cushion_query_vars' );

function lp_service_add_cushion_rewrite_rule() {
	add_rewrite_rule(
		'^go/([0-9]+)/([0-9]+)/?$',
		'index.php?lp_cushion_post=$matches[1]&lp_cushion_button=$matches[2]',
		'top'
	);
}
add_action( 'init', 'lp_service_add_cushion_rewrite_rule', 20 );

/**
 * ブロックの描画元の投稿ID
 *
 * 比較テーブル・ランキング・絞り込み結果は、LPのページ上で商品の本文ブロックを描画する。
 * そのときget_the_ID()はLPのIDになってしまうため、商品のブロックを描画する間だけ
 * lp_service_render_block_for_post()で描画元の商品IDを積んでおく。
 */
function lp_service_cushion_source_stack( $action = 'get', $post_id = 0 ) {
	static $stack = array();
	if ( 'push' === $action ) {
		$stack[] = (int) $post_id;
	} elseif ( 'pop' === $action ) {
		array_pop( $stack );
	}
	return $stack ? end( $stack ) : 0;
}

function lp_service_render_block_for_post( $block, $post_id ) {
	lp_service_cushion_source_stack( 'push', $post_id );
	$html = render_block( $block );
	lp_service_cushion_source_stack( 'pop' );
	return $html;
}

/**
 * ボタンのリンクを表示時に調整する（保存済みHTMLは変えず、表示するときだけ書き換える）
 * - 「新しいタブで開く」（属性 openInNewTab、既定ON）なら target="_blank" を付ける
 * - クッションページONならリンク先をクッションページURLに差し替える
 */
function lp_service_cushion_render_block( $block_content, $block ) {
	$blocks = lp_service_cushion_blocks();
	$name   = $block['blockName'] ?? '';
	if ( ! isset( $blocks[ $name ] ) || is_admin() || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $processor->next_tag( array( 'tag_name' => 'A' ) ) ) {
		return $block_content;
	}

	$attrs = $block['attrs'] ?? array();
	$url   = $attrs[ $blocks[ $name ] ] ?? '';
	if ( '' === $url || '#' === $url ) {
		return $block_content;
	}

	// 既定値（true）の属性はブロックコメントに保存されないため、未設定はONとして扱う
	if ( ! isset( $attrs['openInNewTab'] ) || $attrs['openInNewTab'] ) {
		$processor->set_attribute( 'target', '_blank' );
		$processor->set_attribute( 'rel', 'noopener' );
	}

	$button_no = (int) ( $attrs['buttonNo'] ?? 0 );
	if ( ! empty( $attrs['cushion'] ) && $button_no ) {
		$post_id = lp_service_cushion_source_stack();
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( $post_id ) {
			$processor->set_attribute( 'href', lp_service_get_cushion_url( $post_id, $button_no ) );
		}
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'lp_service_cushion_render_block', 10, 2 );

/**
 * 投稿内から、指定番号のクッションページONのボタンを探す
 */
function lp_service_find_cushion_button( $blocks, $button_no ) {
	$targets = lp_service_cushion_blocks();
	foreach ( $blocks as $block ) {
		$name = $block['blockName'] ?? '';
		if ( isset( $targets[ $name ] ) && ! empty( $block['attrs']['cushion'] ) && (int) ( $block['attrs']['buttonNo'] ?? 0 ) === $button_no ) {
			$block['lp_target_url'] = $block['attrs'][ $targets[ $name ] ] ?? '';
			return $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = lp_service_find_cushion_button( $block['innerBlocks'], $button_no );
			if ( $found ) {
				return $found;
			}
		}
	}
	return null;
}

/**
 * クッションページの出力
 * テーマのヘッダー・CSS・wp_head()は使わず、タグと移動用の最小限のHTMLだけを返す
 */
function lp_service_cushion_template_redirect() {
	$post_id = (int) get_query_var( 'lp_cushion_post' );
	if ( ! $post_id ) {
		return;
	}
	$button_no = (int) get_query_var( 'lp_cushion_button' );
	$post      = get_post( $post_id );

	$visible = $post
		&& in_array( $post->post_type, array( 'service', 'product' ), true )
		&& ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post_id ) );

	$button = $visible ? lp_service_find_cushion_button( parse_blocks( $post->post_content ), $button_no ) : null;
	$target = $button ? esc_url_raw( $button['lp_target_url'] ) : '';

	if ( ! $target || '#' === $target ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	// 個別タグがあればそれだけを出し、無ければ選択したタグを出す
	$tag_code = (string) ( $button['attrs']['cvTagCustom'] ?? '' );
	if ( '' === trim( $tag_code ) ) {
		$tag_code = '';
		$tag_id   = (int) ( $button['attrs']['cvTagId'] ?? 0 );
		$tag_post = $tag_id ? get_post( $tag_id ) : null;
		if ( $tag_post && 'lp_cv_tag' === $tag_post->post_type && 'publish' === $tag_post->post_status ) {
			$tag_code = (string) get_post_meta( $tag_id, '_lp_cv_tag_code', true );
		}
	}

	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Content-Type: text/html; charset=UTF-8' );
	?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>移動中です…</title>
<noscript><meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $target ); ?>"></noscript>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fff;color:#333;font-family:-apple-system,BlinkMacSystemFont,"Hiragino Sans","Hiragino Kaku Gothic ProN",Meiryo,sans-serif;text-align:center}
.lp-cushion{padding:24px 16px}
.lp-cushion__spinner{width:32px;height:32px;margin:0 auto 16px;border:3px solid #ddd;border-top-color:#888;border-radius:50%;animation:lp-cushion-spin 1s linear infinite}
.lp-cushion__text{margin:0 0 12px;font-size:16px}
.lp-cushion__link{font-size:14px;color:#2271b1}
@keyframes lp-cushion-spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<?php echo $tag_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理者が登録したコンバージョンタグをそのまま出力する ?>
<div class="lp-cushion">
	<div class="lp-cushion__spinner" aria-hidden="true"></div>
	<p class="lp-cushion__text">移動中です…</p>
	<a class="lp-cushion__link" href="<?php echo esc_url( $target ); ?>">自動で移動しない場合はこちら</a>
</div>
<script>
(function () {
	var url = <?php echo wp_json_encode( $target ); ?>;
	var done = false;
	function go() {
		if ( done ) { return; }
		done = true;
		window.location.replace( url );
	}
	// タグの読み込み（ページのload）が終わったら移動。読み込みが終わらない場合も最大5秒で移動する
	if ( document.readyState === 'complete' ) {
		setTimeout( go, 300 );
	} else {
		window.addEventListener( 'load', function () { setTimeout( go, 300 ); } );
	}
	setTimeout( go, 5000 );
})();
</script>
</body>
</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'lp_service_cushion_template_redirect', 1 );
