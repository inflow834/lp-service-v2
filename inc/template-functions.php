<?php
/**
 * テンプレート・ブロックrender.phpから使う共通ヘルパー関数
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 指定LPに属する商品を取得
 *
 * @param int    $service_id LP(service)の投稿ID
 * @param array  $criteria   絞り込み条件 array( 'select1' => '値', 'select2' => '値', 'checkbox1'..'checkbox4' => true/false )
 * @param array  $extra_args WP_Query追加引数
 */
function lp_service_get_products_for_service( $service_id, $criteria = array(), $extra_args = array() ) {
	$meta_query = array(
		array(
			'key'   => '_lp_service_id',
			'value' => absint( $service_id ),
		),
	);

	foreach ( array( 'select1', 'select2' ) as $key ) {
		if ( ! empty( $criteria[ $key ] ) ) {
			$meta_query[] = array(
				'key'   => '_lp_filter_' . $key,
				'value' => sanitize_text_field( $criteria[ $key ] ),
			);
		}
	}

	for ( $i = 1; $i <= 4; $i++ ) {
		$key = 'checkbox' . $i;
		if ( ! empty( $criteria[ $key ] ) ) {
			$meta_query[] = array(
				'key'   => '_lp_filter_checkbox' . $i,
				'value' => '1',
			);
		}
	}

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => $meta_query,
	);

	return new WP_Query( array_merge( $args, $extra_args ) );
}

/**
 * LPの絞り込みフォーム設定（セレクト2種＋チェックボックス4種）を取得
 */
function lp_service_get_filter_config( $service_id ) {
	$config = array();

	foreach ( array( 'select1', 'select2' ) as $key ) {
		$config[ $key ] = array(
			'label'   => (string) get_post_meta( $service_id, '_lp_filter_' . $key . '_label', true ),
			'options' => (array) get_post_meta( $service_id, '_lp_filter_' . $key . '_options', true ),
		);
	}

	for ( $i = 1; $i <= 4; $i++ ) {
		$config[ 'checkbox' . $i ] = array(
			'label' => (string) get_post_meta( $service_id, '_lp_filter_checkbox' . $i . '_label', true ),
		);
	}

	return $config;
}

/**
 * 現在のリクエストの絞り込み条件を$_GETから取得（絞り込みページ表示中のみ値が入る）
 */
function lp_service_get_current_filter_criteria() {
	$criteria = array(
		'select1' => isset( $_GET['select1'] ) ? sanitize_text_field( wp_unslash( $_GET['select1'] ) ) : '',
		'select2' => isset( $_GET['select2'] ) ? sanitize_text_field( wp_unslash( $_GET['select2'] ) ) : '',
	);
	for ( $i = 1; $i <= 4; $i++ ) {
		$criteria[ 'checkbox' . $i ] = ! empty( $_GET[ 'checkbox' . $i ] );
	}
	return $criteria;
}

/**
 * 絞り込みフォームのHTMLを生成（LP本文ブロック／絞り込みページの両方から使用）
 * フォームはGET送信で、送信先は必ずそのLPの絞り込みページ（{LPのURL}search/）。
 */
function lp_service_render_filter_form( $service_id, $heading = 'あなたにぴったりの買取サービスを探す', $button_text = 'この条件で検索する' ) {
	$config       = lp_service_get_filter_config( $service_id );
	$is_search    = (bool) get_query_var( 'lp_service_search' );
	$current      = $is_search ? lp_service_get_current_filter_criteria() : array();
	$search_url   = trailingslashit( get_permalink( $service_id ) ) . 'search/';

	ob_start();
	?>
	<div class="lp-filter-form-block">
		<form class="lp-filter-form" method="get" action="<?php echo esc_url( $search_url ); ?>">
			<?php if ( $heading ) : ?>
				<div class="lp-filter-form__header"><?php echo esc_html( $heading ); ?></div>
			<?php endif; ?>
			<div class="lp-filter-form__body">
				<?php
				$has_select = ( '' !== $config['select1']['label'] ) || ( '' !== $config['select2']['label'] );
				if ( $has_select ) :
					?>
					<div class="lp-filter-form__row lp-filter-form__row--select">
						<?php foreach ( array( 'select1', 'select2' ) as $key ) : ?>
							<?php if ( '' === $config[ $key ]['label'] ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<select class="lp-filter-form__select" name="<?php echo esc_attr( $key ); ?>">
								<option value=""><?php echo esc_html( $config[ $key ]['label'] ); ?>を選択</option>
								<?php foreach ( $config[ $key ]['options'] as $option ) : ?>
									<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $current[ $key ] ?? '', $option ); ?>><?php echo esc_html( $option ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				$checkbox_labels = array();
				for ( $i = 1; $i <= 4; $i++ ) {
					if ( '' !== $config[ 'checkbox' . $i ]['label'] ) {
						$checkbox_labels[ $i ] = $config[ 'checkbox' . $i ]['label'];
					}
				}
				if ( $checkbox_labels ) :
					$chunks = array_chunk( $checkbox_labels, 2, true );
					foreach ( $chunks as $chunk ) :
						?>
						<div class="lp-filter-form__row lp-filter-form__row--checkbox">
							<?php foreach ( $chunk as $i => $label ) : ?>
								<label class="lp-filter-form__checkbox">
									<input type="checkbox" name="checkbox<?php echo (int) $i; ?>" value="1" <?php checked( ! empty( $current[ 'checkbox' . $i ] ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<?php
					endforeach;
				endif;
				?>

				<button type="submit" class="lp-filter-form__submit"><?php echo esc_html( $button_text ); ?></button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * パース済みブロック配列から、最初に一致するブロックを再帰的に探す
 */
function lp_service_find_block( $blocks, $block_name ) {
	foreach ( $blocks as $block ) {
		if ( isset( $block['blockName'] ) && $block['blockName'] === $block_name ) {
			return $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = lp_service_find_block( $block['innerBlocks'], $block_name );
			if ( $found ) {
				return $found;
			}
		}
	}
	return null;
}

/**
 * パース済みブロック配列から、一致する全ブロックを再帰的に探す
 */
function lp_service_find_blocks( $blocks, $block_name ) {
	$results = array();
	foreach ( $blocks as $block ) {
		if ( isset( $block['blockName'] ) && $block['blockName'] === $block_name ) {
			$results[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$results = array_merge( $results, lp_service_find_blocks( $block['innerBlocks'], $block_name ) );
		}
	}
	return $results;
}

/**
 * 指定商品の「テーブル」ブロック(6項目)の内容を取得
 */
function lp_service_get_product_table_items( $product_id ) {
	$post = get_post( $product_id );
	if ( ! $post ) {
		return array();
	}
	$blocks = parse_blocks( $post->post_content );
	$table  = lp_service_find_block( $blocks, 'lp-service/product-table' );
	if ( ! $table || empty( $table['attrs']['items'] ) ) {
		return array();
	}
	return $table['attrs']['items'];
}

/**
 * 星評価(5段階固定)をHTML出力
 */
function lp_service_render_star_rating( $rating ) {
	$rating = max( 0, min( 5, (int) $rating ) );
	$html   = '<span class="lp-star-rating" role="img" aria-label="' . esc_attr( $rating . ' / 5' ) . '">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$icon  = $i <= $rating ? lp_service_get_icon_svg( 'star_filled' ) : lp_service_get_icon_svg( 'star_empty' );
		$html .= '<span class="lp-star-rating__star' . ( $i <= $rating ? ' is-filled' : '' ) . '">' . $icon . '</span>';
	}
	$html .= '</span>';
	return $html;
}

/**
 * 商品編集画面に登録した内容（タイトル・キャッチコピー・アイキャッチ・全ブロック）を
 * すべて展開したHTMLを生成する。人気ランキング・絞り込みページ結果の両方で使用。
 */
function lp_service_render_product_full( $product_id ) {
	$post = get_post( $product_id );
	if ( ! $post ) {
		return '';
	}

	$catch_copy = get_post_meta( $product_id, '_lp_catch_copy', true );
	$blocks     = parse_blocks( $post->post_content );

	ob_start();
	?>
	<div class="lp-product-full" data-product-id="<?php echo esc_attr( $product_id ); ?>">
		<div class="lp-product-full__head">
			<p class="lp-product-full__title"><?php echo esc_html( get_the_title( $product_id ) ); ?></p>
			<?php if ( $catch_copy ) : ?>
				<p class="lp-product-full__catch"><?php echo esc_html( $catch_copy ); ?></p>
			<?php endif; ?>
			<?php if ( has_post_thumbnail( $product_id ) ) : ?>
				<div class="lp-product-full__thumb"><?php echo get_the_post_thumbnail( $product_id, 'large' ); ?></div>
			<?php endif; ?>
		</div>
		<div class="lp-product-full__content">
			<?php foreach ( $blocks as $block ) : ?>
				<?php echo render_block( $block ); // phpcs:ignore ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * LPページ全体のレイアウトとして選択可能なデザインテンプレート一覧
 * キーは template-parts/service/design-{キー}.php のファイル名に対応する
 */
function lp_service_get_design_templates() {
	return array(
		'standard'     => 'スタンダード',
		'simple'       => 'シンプル',
		'simple-pop'   => 'シンプル・ポップ（スマホ専用）',
		'simple-trust' => 'シンプル・トラスト（スマホ専用）',
	);
}

/**
 * 各デザインテンプレート選択時にブロックエディタへ自動配置する初期ブロック構成
 * ブロックエディタ側（assets/js/design-template-switcher.js）へJSONで渡され、
 * 本文が空の場合のみ、デザインテンプレート切り替え時にこの構成が挿入される。
 * free-section・pointsは各ブロック自身のInnerBlocksテンプレートで中身が自動補完される。
 */
function lp_service_get_design_block_templates() {
	return array(
		'standard' => array(
			array( 'lp-service/free-section' ),
			array( 'lp-service/points' ),
			array( 'lp-service/comparison-table' ),
			array( 'lp-service/ranking' ),
			array( 'lp-service/cta' ),
			array( 'lp-service/filter-form' ),
		),
		'simple'   => array(
			array( 'lp-service/simple-banner' ),
			array( 'lp-service/simple-button' ),
			array( 'lp-service/simple-description' ),
			array( 'lp-service/simple-heading' ),
			array( 'lp-service/simple-description' ),
			array( 'lp-service/simple-button' ),
		),
		'simple-pop'   => lp_service_get_simple_block_template( '#ff7a1a', '#ffffff' ),
		'simple-trust' => lp_service_get_simple_block_template( '#1f3a5f', '#ffffff' ),
	);
}

/**
 * スマホ専用の「シンプル」系デザインの初期ブロック構成
 * ブロックの並びは「シンプル」と同じで、ボタン色だけをデザインの配色に合わせる。
 * ボタン色はブロック属性として保存されるため、あとからブロック側で個別に変更できる。
 */
function lp_service_get_simple_block_template( $button_color, $button_text_color ) {
	$button = array(
		'lp-service/simple-button',
		array(
			'color'     => $button_color,
			'textColor' => $button_text_color,
		),
	);

	return array(
		array( 'lp-service/simple-banner' ),
		$button,
		array( 'lp-service/simple-description' ),
		array( 'lp-service/simple-heading' ),
		array( 'lp-service/simple-description' ),
		$button,
	);
}

/**
 * LP本体ページの<body>に選択中のデザインのクラス（lp-design-{slug}）を付ける
 * スマホ専用デザインはこのクラスを起点に、幅やPC表示時の左右の背景色を切り替える
 * （assets/css/design-mobile.css）。絞り込みページ（/search/）はデザインに関係なく共通レイアウトのため対象外。
 */
function lp_service_design_body_class( $classes ) {
	if ( is_singular( 'service' ) && ! get_query_var( 'lp_service_search' ) ) {
		$classes[] = 'lp-design-' . lp_service_get_design_template( get_queried_object_id() );
	}
	return $classes;
}
add_filter( 'body_class', 'lp_service_design_body_class' );

/**
 * 指定LPに設定されているデザインテンプレートのスラッグを取得（未設定・不正値はstandardにフォールバック）
 */
function lp_service_get_design_template( $service_id ) {
	$templates = lp_service_get_design_templates();
	$design    = get_post_meta( $service_id, '_lp_design_template', true );

	if ( ! $design || ! isset( $templates[ $design ] ) ) {
		$design = 'standard';
	}

	return $design;
}

/**
 * サービス(LP)のヘッダー情報をまとめて取得
 */
function lp_service_get_header_data( $service_id ) {
	return array(
		'logo_type'    => get_post_meta( $service_id, '_lp_header_logo_type', true ) ?: 'text',
		'logo_text'    => get_post_meta( $service_id, '_lp_header_logo_text', true ),
		'logo_image'   => (int) get_post_meta( $service_id, '_lp_header_logo_image', true ),
		'catch_copy'   => get_post_meta( $service_id, '_lp_header_catch_copy', true ),
		'banner_image' => (int) get_post_meta( $service_id, '_lp_header_banner_image', true ),
	);
}
