<?php
/**
 * クラシックメタボックス: service ヘッダー情報 / product 所属LP・キャッチコピー
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lp_service_add_meta_boxes() {
	add_meta_box(
		'lp_service_design',
		'デザインテンプレート',
		'lp_service_render_design_meta_box',
		'service',
		'side',
		'high'
	);

	add_meta_box(
		'lp_service_header',
		'ヘッダーセクション',
		'lp_service_render_header_meta_box',
		'service',
		'normal',
		'high'
	);

	add_meta_box(
		'lp_service_filter_settings',
		'絞り込みフォーム設定',
		'lp_service_render_filter_settings_meta_box',
		'service',
		'normal',
		'default'
	);

	add_meta_box(
		'lp_service_operator',
		'運営者情報リンク設定',
		'lp_service_render_operator_meta_box',
		'service',
		'normal',
		'default'
	);

	add_meta_box(
		'lp_product_info',
		'商品情報',
		'lp_service_render_product_info_meta_box',
		'product',
		'side',
		'high'
	);

	add_meta_box(
		'lp_product_filter_values',
		'絞り込み条件',
		'lp_service_render_product_filter_meta_box',
		'product',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'lp_service_add_meta_boxes' );

/**
 * LPページ全体のレイアウトデザインを選択するメタボックス
 */
function lp_service_render_design_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_design', 'lp_service_design_nonce' );

	$templates = lp_service_get_design_templates();
	// 未設定時に「スタンダード」が既定選択されてしまうと選び直しができなくなるため、
	// ここではフォールバックしない生のメタ値を使う（空なら「デザインを選択してください」を表示）。
	$current = (string) get_post_meta( $post->ID, '_lp_design_template', true );
	?>
	<p>
		<select name="lp_design_template" id="lp_design_template" class="widefat">
			<option value="" <?php selected( $current, '' ); ?>>デザインを選択してください</option>
			<?php foreach ( $templates as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">LPページ全体のレイアウトデザインを選択します。切り替えると本文のブロックが選択したデザインの初期ブロックに置き換わります（確認ダイアログが表示されます）。</p>
	<?php
}

function lp_service_render_header_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_header', 'lp_service_header_nonce' );

	$logo_type    = get_post_meta( $post->ID, '_lp_header_logo_type', true ) ?: 'text';
	$logo_text    = get_post_meta( $post->ID, '_lp_header_logo_text', true );
	$logo_image   = (int) get_post_meta( $post->ID, '_lp_header_logo_image', true );
	$catch_copy   = get_post_meta( $post->ID, '_lp_header_catch_copy', true );
	$banner_image = (int) get_post_meta( $post->ID, '_lp_header_banner_image', true );
	?>
	<p>
		<label><strong>サイトロゴ形式</strong></label><br>
		<label><input type="radio" name="lp_header_logo_type" value="text" <?php checked( $logo_type, 'text' ); ?>> テキスト</label>
		<label style="margin-left:16px;"><input type="radio" name="lp_header_logo_type" value="image" <?php checked( $logo_type, 'image' ); ?>> 画像</label>
	</p>
	<p>
		<label for="lp_header_logo_text"><strong>ロゴテキスト</strong></label><br>
		<input type="text" id="lp_header_logo_text" name="lp_header_logo_text" class="widefat" value="<?php echo esc_attr( $logo_text ); ?>">
	</p>
	<p>
		<label><strong>ロゴ画像</strong></label><br>
		<?php lp_service_media_picker_field( 'lp_header_logo_image', $logo_image ); ?>
	</p>
	<p>
		<label for="lp_header_catch_copy"><strong>キャッチコピー</strong></label><br>
		<input type="text" id="lp_header_catch_copy" name="lp_header_catch_copy" class="widefat" value="<?php echo esc_attr( $catch_copy ); ?>">
	</p>
	<p>
		<label><strong>バナー画像</strong></label><br>
		<?php lp_service_media_picker_field( 'lp_header_banner_image', $banner_image ); ?>
	</p>
	<?php
}

/**
 * LPごとの絞り込みフォーム設定（セレクト2種＋チェックボックス4種）
 */
function lp_service_render_filter_settings_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_filter_settings', 'lp_service_filter_settings_nonce' );

	$config = lp_service_get_filter_config( $post->ID );
	?>
	<p class="description">ここで設定した項目が、LP本文に挿入する「絞り込みフォーム」ブロックと商品編集画面の入力項目に反映されます。</p>

	<h4>セレクトボックス項目</h4>
	<?php foreach ( array( 'select1', 'select2' ) as $key ) : ?>
		<p>
			<label><strong><?php echo esc_html( $key === 'select1' ? 'セレクト1 ラベル' : 'セレクト2 ラベル' ); ?></strong></label><br>
			<input type="text" name="lp_filter_<?php echo esc_attr( $key ); ?>_label" class="widefat" value="<?php echo esc_attr( $config[ $key ]['label'] ); ?>" placeholder="例: エリア">
		</p>
		<p>
			<label><strong>選択肢（1行に1つ）</strong></label><br>
			<textarea name="lp_filter_<?php echo esc_attr( $key ); ?>_options" class="widefat" rows="4" placeholder="北海道&#10;東北&#10;関東"><?php echo esc_textarea( implode( "\n", $config[ $key ]['options'] ) ); ?></textarea>
		</p>
	<?php endforeach; ?>

	<h4>チェックボックス項目</h4>
	<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
		<p>
			<label><strong>チェックボックス<?php echo (int) $i; ?> ラベル</strong></label><br>
			<input type="text" name="lp_filter_checkbox<?php echo (int) $i; ?>_label" class="widefat" value="<?php echo esc_attr( $config[ 'checkbox' . $i ]['label'] ); ?>" placeholder="例: 出張買取">
		</p>
	<?php endfor; ?>
	<?php
}

/**
 * LPのフッターにリンクする運営者情報を選択するメタボックス
 */
function lp_service_render_operator_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_operator', 'lp_service_operator_nonce' );

	$current = (int) get_post_meta( $post->ID, '_lp_operator_id', true );

	$operators = get_posts(
		array(
			'post_type'      => 'operator_info',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<p class="description">選択すると、このLPのフッターに「運営者情報」へのリンクが表示されます。</p>
	<p>
		<select name="lp_operator_id" class="widefat">
			<option value="">選択しない</option>
			<?php foreach ( $operators as $operator ) : ?>
				<option value="<?php echo esc_attr( $operator->ID ); ?>" <?php selected( $current, $operator->ID ); ?>>
					<?php echo esc_html( $operator->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

function lp_service_render_product_info_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_product_info', 'lp_service_product_info_nonce' );

	$service_id = (int) get_post_meta( $post->ID, '_lp_service_id', true );
	$catch_copy = get_post_meta( $post->ID, '_lp_catch_copy', true );

	$services = get_posts(
		array(
			'post_type'      => 'service',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<p>
		<label for="lp_product_service_id"><strong>所属LP <span style="color:#d63638;">*</span></strong></label><br>
		<select id="lp_product_service_id" name="lp_product_service_id" class="widefat" required>
			<option value="">選択してください</option>
			<?php foreach ( $services as $service ) : ?>
				<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( $service_id, $service->ID ); ?>>
					<?php echo esc_html( $service->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="lp_product_catch_copy"><strong>キャッチコピー</strong></label><br>
		<input type="text" id="lp_product_catch_copy" name="lp_product_catch_copy" class="widefat" value="<?php echo esc_attr( $catch_copy ); ?>">
	</p>
	<p class="description">商品名は上部の「タイトル」欄、画像は「アイキャッチ画像」欄を使用してください。</p>
	<?php
}

/**
 * 商品ごとの絞り込み条件値（所属LPの「絞り込みフォーム設定」に連動）
 */
function lp_service_render_product_filter_meta_box( $post ) {
	wp_nonce_field( 'lp_service_save_product_filter', 'lp_service_product_filter_nonce' );

	$service_id = (int) get_post_meta( $post->ID, '_lp_service_id', true );

	if ( ! $service_id || 'service' !== get_post_type( $service_id ) ) {
		echo '<p class="description">先に左側の「所属LP」を選択し、一度更新（保存）すると、LPで設定した絞り込み項目がここに表示されます。</p>';
		return;
	}

	$config = lp_service_get_filter_config( $service_id );

	foreach ( array( 'select1', 'select2' ) as $key ) {
		if ( '' === $config[ $key ]['label'] ) {
			continue;
		}
		$current = (array) get_post_meta( $post->ID, '_lp_filter_' . $key, false );
		?>
		<p>
			<label><strong><?php echo esc_html( $config[ $key ]['label'] ); ?></strong></label><br>
			<select name="lp_filter_<?php echo esc_attr( $key ); ?>[]" class="widefat" multiple size="<?php echo (int) max( 3, count( $config[ $key ]['options'] ) ); ?>">
				<?php foreach ( $config[ $key ]['options'] as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php echo in_array( $option, $current, true ) ? 'selected' : ''; ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description">Ctrl（Macはcommand）キーを押しながらクリックすると複数選択できます。</span>
		</p>
		<?php
	}

	$has_checkbox = false;
	for ( $i = 1; $i <= 4; $i++ ) {
		if ( '' !== $config[ 'checkbox' . $i ]['label'] ) {
			$has_checkbox = true;
			break;
		}
	}

	if ( $has_checkbox ) {
		echo '<p><strong>該当する条件にチェック</strong></p>';
		for ( $i = 1; $i <= 4; $i++ ) {
			$label = $config[ 'checkbox' . $i ]['label'];
			if ( '' === $label ) {
				continue;
			}
			$checked = (bool) get_post_meta( $post->ID, '_lp_filter_checkbox' . $i, true );
			?>
			<p>
				<label>
					<input type="checkbox" name="lp_filter_checkbox<?php echo (int) $i; ?>" value="1" <?php checked( $checked ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
			<?php
		}
	}

	if ( '' === $config['select1']['label'] && '' === $config['select2']['label'] && ! $has_checkbox ) {
		echo '<p class="description">所属LPの編集画面で「絞り込みフォーム設定」を入力すると、ここに入力欄が表示されます。</p>';
	}
}

/**
 * メディアアップローダー付きの画像選択フィールド共通パーツ
 */
function lp_service_media_picker_field( $name, $image_id ) {
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<div class="lp-service-media-field" data-target="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" class="lp-service-media-field__input" value="<?php echo esc_attr( $image_id ); ?>">
		<div class="lp-service-media-field__preview" style="margin-bottom:8px;">
			<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:150px;height:auto;<?php echo $image_url ? '' : 'display:none;'; ?>">
		</div>
		<button type="button" class="button lp-service-media-field__select">画像を選択</button>
		<button type="button" class="button lp-service-media-field__remove" style="<?php echo $image_url ? '' : 'display:none;'; ?>">削除</button>
	</div>
	<?php
}

function lp_service_save_meta_boxes( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'service' === $post->post_type && isset( $_POST['lp_service_design_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_design_nonce'], 'lp_service_save_design' ) && current_user_can( 'edit_post', $post_id ) ) {

		$templates = lp_service_get_design_templates();
		$design    = sanitize_key( $_POST['lp_design_template'] ?? '' );
		if ( '' !== $design && ! isset( $templates[ $design ] ) ) {
			$design = '';
		}
		update_post_meta( $post_id, '_lp_design_template', $design );
	}

	if ( 'service' === $post->post_type && isset( $_POST['lp_service_header_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_header_nonce'], 'lp_service_save_header' ) && current_user_can( 'edit_post', $post_id ) ) {

		update_post_meta( $post_id, '_lp_header_logo_type', sanitize_text_field( $_POST['lp_header_logo_type'] ?? 'text' ) );
		update_post_meta( $post_id, '_lp_header_logo_text', sanitize_text_field( $_POST['lp_header_logo_text'] ?? '' ) );
		update_post_meta( $post_id, '_lp_header_logo_image', absint( $_POST['lp_header_logo_image'] ?? 0 ) );
		update_post_meta( $post_id, '_lp_header_catch_copy', sanitize_text_field( $_POST['lp_header_catch_copy'] ?? '' ) );
		update_post_meta( $post_id, '_lp_header_banner_image', absint( $_POST['lp_header_banner_image'] ?? 0 ) );
	}

	if ( 'service' === $post->post_type && isset( $_POST['lp_service_operator_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_operator_nonce'], 'lp_service_save_operator' ) && current_user_can( 'edit_post', $post_id ) ) {

		update_post_meta( $post_id, '_lp_operator_id', absint( $_POST['lp_operator_id'] ?? 0 ) );
	}

	if ( 'product' === $post->post_type && isset( $_POST['lp_service_product_info_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_product_info_nonce'], 'lp_service_save_product_info' ) && current_user_can( 'edit_post', $post_id ) ) {

		update_post_meta( $post_id, '_lp_service_id', absint( $_POST['lp_product_service_id'] ?? 0 ) );
		update_post_meta( $post_id, '_lp_catch_copy', sanitize_text_field( $_POST['lp_product_catch_copy'] ?? '' ) );
	}

	if ( 'service' === $post->post_type && isset( $_POST['lp_service_filter_settings_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_filter_settings_nonce'], 'lp_service_save_filter_settings' ) && current_user_can( 'edit_post', $post_id ) ) {

		foreach ( array( 'select1', 'select2' ) as $key ) {
			update_post_meta( $post_id, '_lp_filter_' . $key . '_label', sanitize_text_field( $_POST[ 'lp_filter_' . $key . '_label' ] ?? '' ) );

			$raw_options = (string) ( $_POST[ 'lp_filter_' . $key . '_options' ] ?? '' );
			$options     = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_options ) ) ) );
			$options     = array_map( 'sanitize_text_field', $options );
			update_post_meta( $post_id, '_lp_filter_' . $key . '_options', $options );
		}

		for ( $i = 1; $i <= 4; $i++ ) {
			update_post_meta( $post_id, '_lp_filter_checkbox' . $i . '_label', sanitize_text_field( $_POST[ 'lp_filter_checkbox' . $i . '_label' ] ?? '' ) );
		}
	}

	if ( 'product' === $post->post_type && isset( $_POST['lp_service_product_filter_nonce'] ) &&
		wp_verify_nonce( $_POST['lp_service_product_filter_nonce'], 'lp_service_save_product_filter' ) && current_user_can( 'edit_post', $post_id ) ) {

		foreach ( array( 'select1', 'select2' ) as $key ) {
			delete_post_meta( $post_id, '_lp_filter_' . $key );
			$values = isset( $_POST[ 'lp_filter_' . $key ] ) ? (array) $_POST[ 'lp_filter_' . $key ] : array();
			foreach ( $values as $value ) {
				$value = sanitize_text_field( $value );
				if ( '' !== $value ) {
					add_post_meta( $post_id, '_lp_filter_' . $key, $value, false );
				}
			}
		}
		for ( $i = 1; $i <= 4; $i++ ) {
			update_post_meta( $post_id, '_lp_filter_checkbox' . $i, isset( $_POST[ 'lp_filter_checkbox' . $i ] ) ? 1 : 0 );
		}
	}
}
add_action( 'save_post', 'lp_service_save_meta_boxes', 10, 2 );

/**
 * 管理画面: メディアアップローダーのJSを読み込み
 */
function lp_service_admin_enqueue( $hook ) {
	global $post_type;
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && in_array( $post_type, array( 'service', 'product' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script(
			'lp-service-admin-meta',
			LP_SERVICE_URI . '/assets/js/admin-meta.js',
			array( 'jquery' ),
			LP_SERVICE_VERSION,
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'lp_service_admin_enqueue' );

/**
 * ブロックエディタ: 「デザインテンプレート」セレクト切り替え時のブロック自動配置スクリプト
 */
function lp_service_enqueue_design_switcher() {
	$screen = get_current_screen();
	if ( ! $screen || 'service' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'lp-service-design-switcher',
		LP_SERVICE_URI . '/assets/js/design-template-switcher.js',
		array( 'wp-blocks', 'wp-data' ),
		LP_SERVICE_VERSION,
		true
	);

	wp_localize_script(
		'lp-service-design-switcher',
		'lpServiceDesignBlockTemplates',
		lp_service_get_design_block_templates()
	);
}
add_action( 'enqueue_block_editor_assets', 'lp_service_enqueue_design_switcher' );

/**
 * ブロックエディタ: スマホ専用デザインの見た目（430px幅・配色）をキャンバスに反映するCSS
 * enqueue_block_assets で読み込むとキャンバスのiframe内に入る（add_editor_style と違い、
 * セレクタに .editor-styles-wrapper が前置されないため、iframeの<body>のクラスを起点にできる）。
 * どのデザインのクラスを<body>に付けるかは design-template-switcher.js が切り替える。
 */
function lp_service_enqueue_design_mobile_editor_style() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'service' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style(
		'lp-service-design-mobile',
		LP_SERVICE_URI . '/assets/css/design-mobile.css',
		array(),
		LP_SERVICE_VERSION
	);
}
add_action( 'enqueue_block_assets', 'lp_service_enqueue_design_mobile_editor_style' );
