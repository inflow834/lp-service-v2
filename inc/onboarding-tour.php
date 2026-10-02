<?php
/**
 * 管理画面オンボーディングツアー（V2で追加）
 *
 * LP作成 → 商品作成 → 運営者情報作成 → LPに戻って仕上げ、の4章構成で
 * 実際の管理UI要素にスポットライト＋吹き出しを出すステップツアー。
 *
 * 仕様の全文は docs/tour-steps.md を参照。主なポイント:
 * - ステップは「章」に属し、章単位で単独起動・スキップできる
 * - tier（required / recommended / optional）で入力の強制度を分ける
 * - 前提条件（requires）の充足判定はこのファイルのPHP側で行いJSへ渡す
 * - デザインテンプレート（standard / simple）でステップを出し分ける
 * - 進行状況はサーバーに持たず localStorage のみで管理する
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 章の定義。
 *
 * entry はその章の入口画面。章の単独起動時だけでなく、前の章の章末カードで
 * 「章Nへ進む」を押したときの遷移先にも使う（章4だけは記録済みのLPへ直接戻る）。
 */
function lp_service_get_tour_chapters() {
	return array(
		1 => array(
			'number' => 1,
			'title'  => 'LPを作る',
			'intro'  => 'まずLP本体を1枚作ります。デザインを選ぶと骨組みのブロックが自動で入るので、そこに中身を埋めていきます。',
			'entry'  => 'edit-service',
		),
		2 => array(
			'number' => 2,
			'title'  => '商品を作る',
			'intro'  => 'LPに並べる商品（買取業者など）を登録します。1件作ってみて、あとは同じ手順の繰り返しです。',
			'entry'  => 'edit-product',
		),
		3 => array(
			'number' => 3,
			'title'  => '運営者情報を作る',
			'intro'  => 'LPのフッターからリンクする運営者情報のページを作ります。4ステップで終わります。',
			'entry'  => 'edit-operator_info',
		),
		4 => array(
			'number' => 4,
			'title'  => 'LPに戻って仕上げる',
			'intro'  => '商品と運営者情報が揃ったので、最初に作ったLPに戻って残りを設定します。',
			'entry'  => 'edit-service',
		),
	);
}

/**
 * 章末カード。デザインテンプレートごとに文面を変える場合は design 別に持つ。
 */
function lp_service_get_tour_chapter_outros() {
	return array(
		1 => array(
			'standard' => array(
				'done' => 'LPが1枚できました。',
				'next' => 'いま設定した絞り込み項目が、次の章で<strong>商品側の入力欄としてそのまま出てきます</strong>。',
				'note' => '比較テーブル・人気ランキング・運営者情報リンクは、商品と運営者情報を作ってから章4で設定します。',
			),
			'simple'   => array(
				'done' => 'LPが1枚できました。',
				'next' => '次の章で、このLPに並べる商品を登録します。',
				'note' => 'シンプルデザインには比較テーブル・人気ランキング・絞り込みフォームが初期配置されません。使いたい場合は本文に手動で追加した上で、LPの「絞り込みフォーム設定」を行ってください。運営者情報リンクは章4で設定します。',
			),
		),
		2 => array(
			'*' => array(
				'done'   => '商品がLPに紐づきました。',
				'next'   => 'LPのフッターに載せる運営者情報を作ります。',
				'repeat' => true,
			),
		),
		3 => array(
			'*' => array(
				'done' => '運営者情報ページが公開されました。',
				'next' => '最初に作ったLPに戻って、比較テーブル・ランキング・運営者情報リンクを仕上げます。',
				'note' => '運営者情報のURLは <span class="mono">/operator-info/スラッグ/</span> という形になります（サイト直下のURLになるのはLPだけです）。',
			),
		),
	);
}

/**
 * ステップ定義（章順・画面順に並べたフラット配列）。
 *
 * group      … get_current_screen()->id
 * frame      … 'canvas' でブロックエディタのiframe内を探す
 * expandTo   … ハイライト範囲を祖先要素まで広げる（closestに渡すセレクター）
 * tier       … required（入力するまで次へ進めない）/ recommended（未入力バッジ）/ optional
 * requires   … 前提条件キー。未充足なら emptyBody に差し替える
 * designs    … 表示対象のデザインテンプレート（省略時は全て）
 * awaitPublish … 投稿が実際に publish になるまで「次へ」を無効化し、公開を検知して自動で先へ進む
 *                （Gutenberg の「公開」は確認パネルを挟む2段階操作のため、クリック検知では早すぎる）
 * awaitSave    … 保存が完了する（未保存の変更が無くなる）まで「次へ」を無効化する
 * reloadAfterSave … 保存できたら画面を読み込み直してから次のステップへ進む
 *                   （クラシックメタボックスは保存しただけでは再描画されないため）
 * allowReload  … 吹き出しに「画面を再読み込みする」ボタンを出す
 * targetPanel  … 公開確認パネルが開いたときにハイライトを移す先
 *
 * 画面移動のための「左メニューを押す」ステップは持たない。ブロックエディタが
 * フルスクリーンモードだと左メニューが非表示になり操作不能になるため、章の
 * 切り替えは章末カードの「章Nへ進む」からJSが直接 location を変える。
 */
function lp_service_get_tour_steps() {
	$publish_target = array( '.editor-post-publish-button__button', '.editor-post-publish-panel__toggle', '#publish' );
	$title_target   = array( '.editor-post-title__input', '.wp-block-post-title', '.editor-post-title' );
	// 「下書き保存」は確認パネルを挟まず1クリックで完了する
	$draft_target   = array( '.editor-post-save-draft', '.editor-post-saved-state', '#save-post' );

	return array(

		// ---------------- 章1: LPを作る ----------------
		array(
			'id'      => 'c1-create-lp',
			'chapter' => 1,
			'group'   => 'edit-service',
			'title'   => 'LPを新規作成する',
			'body'    => '「新規追加」からLPを1枚作ります。保存するとURLは <span class="mono">/スラッグ/</span> の形で、サイト直下に作られます。',
			'target'  => array( '.page-title-action' ),
			'tier'    => 'required',
			'navHint' => true,
		),
		array(
			'id'      => 'c1-title',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'タイトルを入力する',
			'body'    => '<strong>このタイトルはLPのページ上には表示されません。</strong>管理画面のLP一覧で「どのLPか」を見分けるための名前なので、分かりやすいものを付けておくのがおすすめです（必須ではありません）。なお空のままだとURLが数字だけになるため、あとから「パーマリンク」欄でURLを整えてください。',
			'target'  => $title_target,
			'frame'   => 'canvas',
			'tier'    => 'recommended',
			'sample'  => 'lp_title',
		),
		array(
			'id'      => 'c1-design',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'デザインテンプレートを選ぶ',
			'body'    => '「スタンダード」「シンプル」「シンプル・ポップ」「シンプル・トラスト」の4種類から選びます。選んだ瞬間に、そのデザインの骨組みブロックが本文へ自動で流し込まれます。<strong>ポップとトラストはスマホ専用</strong>で、ブロックの構成は「シンプル」と同じ（見た目だけが違います）。<strong>選ばないと本文が空のままで、この先の説明ができません。</strong>すでに本文にブロックがある場合は、入れ替えてよいか確認が出ます。',
			'target'  => array( '#lp_design_template' ),
			'tier'    => 'required',
		),

		// ヘッダーセクション（スタンダードのみ）
		array(
			'id'       => 'c1-logo-type',
			'chapter'  => 1,
			'group'    => 'service',
			'title'    => 'サイトロゴの形式を選ぶ',
			'body'     => 'ロゴを「テキスト」で出すか「画像」で出すかを選びます。選んだ方の入力欄だけを次で使います。',
			'target'   => array( 'input[name="lp_header_logo_type"]' ),
			'expandTo' => 'p',
			'tier'     => 'optional',
			'designs'  => array( 'standard' ),
		),
		array(
			'id'          => 'c1-logo',
			'chapter'     => 1,
			'group'       => 'service',
			'title'       => 'ロゴを設定する',
			'body'        => 'ヘッダーの左上に出るロゴです。前のステップで「テキスト」を選んだ場合はロゴテキスト欄を、「画像」を選んだ場合はロゴ画像欄を使います。',
			'target'      => array( '#lp_header_logo_text' ),
			'targetImage' => array( '.lp-service-media-field[data-target="lp_header_logo_image"]' ),
			'expandTo'    => 'p',
			'tier'        => 'optional',
			'designs'     => array( 'standard' ),
		),
		array(
			'id'      => 'c1-catch',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'キャッチコピーを入れる',
			'body'    => 'ヘッダーの一番目立つ場所に出る一文です。',
			'target'  => array( '#lp_header_catch_copy' ),
			'tier'    => 'optional',
			'sample'  => 'lp_catch_copy',
			'designs' => array( 'standard' ),
		),
		array(
			'id'       => 'c1-banner',
			'chapter'  => 1,
			'group'    => 'service',
			'title'    => 'バナー画像を設定する',
			'body'     => 'ヘッダーの下に大きく表示される画像です。',
			'target'   => array( '.lp-service-media-field[data-target="lp_header_banner_image"]' ),
			'expandTo' => 'p',
			'tier'     => 'optional',
			'designs'  => array( 'standard' ),
		),

		// 本文ブロック（スタンダード）
		array(
			'id'      => 'c1-block-free',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '自由入力',
			'body'    => '見出しや文章、画像などを自由に置けるブロックです。WordPress標準のブロックをそのまま使えます。',
			'target'  => array( '[data-type="lp-service/free-section"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-block-points',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'ポイント',
			'body'    => '番号付きで訴求点を並べるブロックです。項目ごとに画像も付けられます。',
			'target'  => array( '[data-type="lp-service/points"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-block-comparison',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '比較テーブル（※後で設定）',
			'body'    => '登録した商品を横並びで比較する表です。<strong>中身は商品を登録してから決めるので、ここでは場所だけ覚えておいてください</strong>（章4で設定します）。',
			'target'  => array( '[data-type="lp-service/comparison-table"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-block-ranking',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '人気ランキング（※後で設定）',
			'body'    => '商品をランキング順に表示するブロックです。<strong>順番は商品を登録してから手動で並び替えます</strong>（章4で設定します）。',
			'target'  => array( '[data-type="lp-service/ranking"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-block-cta',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'CTA',
			'body'    => '見出し・画像・ボタンをまとめた訴求セクションです。ボタンの色はサイドバーの「設定」から変えられます。',
			'target'  => array( '[data-type="lp-service/cta"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-block-filter',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '絞り込みフォーム',
			'body'    => '訪問者が条件を選んで商品を絞り込むフォームです。<strong>この中身は次の「絞り込みフォーム設定」で決めます。</strong>',
			'target'  => array( '[data-type="lp-service/filter-form"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'standard' ),
		),

		// 本文ブロック（シンプル）
		array(
			'id'      => 'c1-block-simple-banner',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'シンプルバナー',
			'body'    => 'ページ最上部に置く画像バナーです。',
			'target'  => array( '[data-type="lp-service/simple-banner"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'simple' ),
		),
		array(
			'id'      => 'c1-block-simple-button',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'シンプルボタン',
			'body'    => '行動を促すボタンです。色とリンク先はサイドバーの「設定」から変えられます。',
			'target'  => array( '[data-type="lp-service/simple-button"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'simple' ),
		),
		array(
			'id'      => 'c1-block-simple-heading',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '見出しと説明文',
			'body'    => '見出しと説明文はセットで使います。初期状態では1組だけ用意されています。',
			'target'  => array( '[data-type="lp-service/simple-heading"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
			'designs' => array( 'simple' ),
		),
		array(
			'id'        => 'c1-block-simple-repeat',
			'chapter'   => 1,
			'group'     => 'service',
			'title'     => '増やしたいときは複製',
			'body'      => 'セクションを増やしたいときは、見出しと説明文のブロックを複製して並べてください。',
			'target'    => array( '[data-type="lp-service/simple-description"]' ),
			'targetNth' => 2,
			'frame'     => 'canvas',
			'tier'      => 'optional',
			'designs'   => array( 'simple' ),
		),

		// スマホ専用デザイン（ポップ・トラスト）だけの説明。ブロック構成は「シンプル」と同じなので
		// 上の simple 用ステップがそのまま出るうえに、幅の違いだけをここで補足する。
		array(
			'id'      => 'c1-mobile-width',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'スマホ専用の幅で表示される',
			'body'    => 'このデザインは<strong>最大430pxの細い列</strong>で表示されます。PCで開いた場合も、中央に細い列が置かれ、左右はデザインの背景色で埋まります。編集画面も同じ幅なので、スマホでの見え方を確認しながら作れます。',
			'target'  => array( 'iframe[name="editor-canvas"]', '.editor-visual-editor' ),
			'tier'    => 'optional',
			'designs' => array( 'simple-pop', 'simple-trust' ),
		),

		// 全デザイン共通: ブロックの追加と、文字の装飾
		array(
			'id'      => 'c1-add-blocks',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'ブロックを足す',
			'body'    => '左上の「＋」から、好きなブロックを好きな場所へ追加できます。<strong>「マイクロコピー」</strong>（＼今がおとく／のようなボタン近くの一言）と、<strong>「マーク付きリスト」</strong>（先頭のマークを丸・四角・チェックマークから選べる箇条書き）もここから選べます。色や種類はサイドバーの「設定」で変えられます。',
			'target'  => array( '.editor-document-tools__inserter-toggle', '.edit-post-header-toolbar__inserter-toggle' ),
			'tier'    => 'optional',
		),
		array(
			'id'      => 'c1-text-decoration',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => '文字を目立たせる（装飾）',
			'body'    => '文章の一部を選択して、ブロックの上に出るツールバーの<strong>「︙（さらに表示）」</strong>を開くと、<strong>黄色マーカー・赤文字・青文字</strong>を付けられます（もう一度選ぶと外れます）。説明文・段落・自由入力の中・マイクロコピー・マーク付きリストで使えます。見出しブロックには付けられません。',
			'target'  => array( '[data-type="lp-service/simple-description"]', '[data-type="lp-service/free-section"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
		),

		// 絞り込みフォーム設定（スタンダードのみ）
		array(
			'id'      => 'c1-filter-select1-label',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'セレクト1のラベルを決める',
			'body'    => '訪問者に選ばせたい条件の名前です（例: 対応エリア）。<strong>ここで決めた名前が、そのまま商品編集画面の入力欄になります。</strong>',
			'target'  => array( '[name="lp_filter_select1_label"]' ),
			'tier'    => 'recommended',
			'sample'  => 'filter_select1',
			'designs' => array( 'standard' ),
		),
		array(
			'id'      => 'c1-filter-select1-options',
			'chapter' => 1,
			'group'   => 'service',
			'title'   => 'セレクト1の選択肢を書く',
			'body'    => '1行に1つ書きます。ここに書いた選択肢が、フォームのプルダウンと商品側の選択肢の両方になります。',
			'target'  => array( '[name="lp_filter_select1_options"]' ),
			'tier'    => 'recommended',
			'sample'  => 'filter_select1',
			'designs' => array( 'standard' ),
		),
		array(
			'id'       => 'c1-filter-select2',
			'chapter'  => 1,
			'group'    => 'service',
			'title'       => 'セレクト2を設定する',
			'body'        => '2つ目の条件です。ラベルと選択肢をセットで設定します。使わない場合は空のままで構いません（フォームにも商品側にも出なくなります）。',
			'target'      => array( '[name="lp_filter_select2_label"]' ),
			'targetUnion' => array( '[name="lp_filter_select2_label"]', '[name="lp_filter_select2_options"]' ),
			'tier'        => 'recommended',
			'sample'   => 'filter_select2',
			'designs'  => array( 'standard' ),
		),
		array(
			'id'       => 'c1-filter-checkboxes',
			'chapter'  => 1,
			'group'    => 'service',
			'title'       => 'チェックボックスを設定する',
			'body'        => '「当てはまる／当てはまらない」で絞り込む条件を最大4つ設定できます。空にした項目は表示されません。',
			'target'      => array( '[name="lp_filter_checkbox1_label"]' ),
			'targetUnion' => array(
				'[name="lp_filter_checkbox1_label"]',
				'[name="lp_filter_checkbox2_label"]',
				'[name="lp_filter_checkbox3_label"]',
				'[name="lp_filter_checkbox4_label"]',
			),
			'tier'        => 'recommended',
			'sample'   => 'filter_checkboxes',
			'designs'  => array( 'standard' ),
		),
		array(
			'id'           => 'c1-publish',
			'chapter'      => 1,
			'group'        => 'service',
			'title'        => '公開する',
			'body'         => '「公開」は<strong>2段階</strong>です。1回目で右側に確認パネルが開き、<strong>パネル内の「公開」をもう一度押すと公開が確定します。</strong>公開が確定するまで「次へ」は押せません。<br>なお<strong>このLPには章4でもう一度戻ってくる</strong>ので、覚えておいてください。',
			'target'       => $publish_target,
			'targetPanel'  => array( '.editor-post-publish-panel .editor-post-publish-button__button', '.editor-post-publish-panel .editor-post-publish-button' ),
			'tier'         => 'required',
			'awaitPublish' => true,
			'recordLp'     => true,
		),

		// ---------------- 章2: 商品を作る ----------------
		// 「商品管理を開く」ような画面移動ステップは置かない。ブロックエディタが
		// フルスクリーンモードだと左メニューが非表示になり詰むため、章末カードの
		// 「章Nへ進む」がJS側で入口画面へ直接遷移する方式にしている。
		array(
			'id'      => 'c2-create',
			'chapter' => 2,
			'group'   => 'edit-product',
			'title'   => '商品を新規作成する',
			'body'    => '新規追加すると、<strong>サンプル文言入りのブロックとダミー画像が最初から入った状態</strong>で始まります。ゼロから作る必要はありません。',
			'target'  => array( '.page-title-action' ),
			'tier'    => 'required',
			'navHint' => true,
		),
		array(
			'id'      => 'c2-title',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => '商品名を入力する',
			'body'    => '商品名・業者名を入れます。<strong>LPのタイトルと違い、この名前は実際にページに表示されます</strong>（比較テーブルの見出し・ランキング・絞り込み結果）。',
			'target'  => $title_target,
			'frame'   => 'canvas',
			'tier'    => 'required',
			'sample'  => 'product_title',
		),
		array(
			'id'      => 'c2-featured',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => 'アイキャッチ画像を差し替える',
			'body'    => '<strong>すでにダミー画像が入っています。</strong>実際のロゴや商品画像に差し替えてください。この画像が比較テーブルとランキングに出ます。',
			'target'  => array( '.editor-post-featured-image', '.editor-post-featured-image__container', '.editor-post-featured-image__toggle', '#postimagediv' ),
			'tier'    => 'optional',
		),
		array(
			'id'      => 'c2-service-id',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => '所属LPを選ぶ',
			'body'    => 'この商品をどのLPに並べるかを指定します。<strong>ここが未選択だと、どのLPにも表示されません。</strong>',
			'target'  => array( '#lp_product_service_id' ),
			'tier'    => 'required',
		),
		array(
			'id'      => 'c2-catch',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => 'キャッチコピーを入れる',
			'body'    => 'ランキングと絞り込み結果で、商品名のすぐ下に出る一文です。比較テーブルには出ません。',
			'target'  => array( '#lp_product_catch_copy' ),
			'tier'    => 'optional',
			'sample'  => 'product_catch_copy',
		),
		array(
			'id'        => 'c2-save',
			'chapter'   => 2,
			'group'     => 'product',
			'title'           => '一度「下書き保存」する',
			'body'            => '<strong>ここで一度保存が必要です。</strong>所属LPを保存して初めて、そのLPで設定した絞り込み項目が下の「絞り込み条件」欄に出てきます。<br>まだ公開しなくてよいので、<strong>1クリックで終わる「下書き保存」</strong>を押してください（「公開」は2段階になるので、最後のステップまで取っておきます）。保存できたら、<strong>ガイドが自動で画面を読み込み直します。</strong>',
			'target'          => $draft_target,
			'tier'            => 'required',
			'awaitSave'       => true,
			'reloadAfterSave' => true,
		),
		array(
			'id'      => 'c2-block-table',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => '比較テーブルの中身',
			'body'    => '6項目の表です。<strong>ここに書いた内容が、LPの比較テーブルの行としてそのまま使われます。</strong>アイコンは二重丸・一重丸・バツから選べます。',
			'target'  => array( '[data-type="lp-service/product-table"]' ),
			'frame'   => 'canvas',
			'tier'    => 'recommended',
		),
		array(
			'id'      => 'c2-block-list',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => '特徴リスト',
			'body'    => '見出し付きの箇条書きです。アイコンはチェック・矢印・ドットから選べます。',
			'target'  => array( '[data-type="lp-service/product-list"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
		),
		array(
			'id'      => 'c2-block-review',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => '口コミ',
			'body'    => '利用者の声を星評価付きで載せられます。',
			'target'  => array( '[data-type="lp-service/product-review"]' ),
			'frame'   => 'canvas',
			'tier'    => 'optional',
		),
		array(
			'id'      => 'c2-block-cta',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => 'CTAボタン',
			'body'    => '申し込みボタンです。<strong>このボタンは比較テーブルの最下段にもそのまま並びます。</strong>色はサイドバーの「設定」から変えられます。',
			'target'  => array( '[data-type="lp-service/product-cta"]' ),
			'frame'   => 'canvas',
			'tier'    => 'recommended',
		),
		array(
			'id'      => 'c2-add-blocks',
			'chapter' => 2,
			'group'   => 'product',
			'title'   => 'ブロックの追加と文字の装飾',
			'body'    => '商品でも、左上の「＋」から<strong>「マイクロコピー」「マーク付きリスト」</strong>を追加できます。また文章を選択して、ツールバーの「︙（さらに表示）」から<strong>黄色マーカー・赤文字・青文字</strong>を付けられます。',
			'target'  => array( '.editor-document-tools__inserter-toggle', '.edit-post-header-toolbar__inserter-toggle' ),
			'tier'    => 'optional',
		),
		array(
			'id'        => 'c2-filter-values',
			'chapter'   => 2,
			'group'     => 'product',
			'title'     => '絞り込み条件にチェックする',
			'body'      => '所属LPで決めた項目が並んでいます。当てはまるものを選んでください。セレクトは Ctrl（Mac は command）を押しながらクリックで複数選べます。',
			'emptyBody' => 'ここが空なのは、<strong>所属LPがまだ選ばれていないか、そのLPの「絞り込みフォーム設定」が空だから</strong>です。この章の「所属LPを選ぶ」で指定して一度保存し、LP側でラベルを決めると、その項目がそのままここに入力欄として現れます。絞り込み機能を使わない場合は、このまま空で問題ありません。',
			'target'      => array( '#lp_product_filter_values' ),
			'tier'        => 'recommended',
			'requires'    => array( 'filterConfigured' ),
			'allowReload' => true,
		),
		array(
			'id'            => 'c2-publish',
			'chapter'       => 2,
			'group'         => 'product',
			'title'         => '公開する',
			'body'          => '公開すると、この商品がLPの比較テーブル・ランキングの選択候補に出てきます。<strong>「公開」は2段階です</strong>（1回目で確認パネルが開き、パネル内の「公開」で確定）。<br><strong>商品は必要な数だけ、同じ手順で繰り返し登録してください。</strong>',
			'target'        => $publish_target,
			'targetPanel'   => array( '.editor-post-publish-panel .editor-post-publish-button__button', '.editor-post-publish-panel .editor-post-publish-button' ),
			'tier'          => 'required',
			'awaitPublish'  => true,
			'recordProduct' => true,
		),

		// ---------------- 章3: 運営者情報を作る ----------------
		array(
			'id'      => 'c3-create',
			'chapter' => 3,
			'group'   => 'edit-operator_info',
			'title'   => '新規作成する',
			'body'    => '「新規追加」から運営者情報ページを作ります。',
			'target'  => array( '.page-title-action' ),
			'tier'    => 'required',
			'navHint' => true,
		),
		array(
			'id'      => 'c3-title',
			'chapter' => 3,
			'group'   => 'operator_info',
			'title'   => 'タイトルを入力する',
			'body'    => '<strong>タイトルが空のままだと「公開」ボタンを押せません。</strong>ページの見出しとして表示されるので「運営者情報」「会社概要」などを入れてください。URLもこのタイトルから作られます（<span class="mono">/operator-info/スラッグ/</span>）。',
			'target'  => $title_target,
			'frame'   => 'canvas',
			'tier'    => 'required',
			'sample'  => 'operator_title',
		),
		array(
			'id'      => 'c3-content',
			'chapter' => 3,
			'group'   => 'operator_info',
			'title'   => '雛形を埋める',
			'body'    => '<strong>運営会社名・代表者名・所在地・電話番号・メールアドレス・事業内容・許可番号の雛形が最初から入っています。</strong>サンプル文言を実際の情報に書き換えてください。',
			'target'  => array( '.block-editor-block-list__layout', '.editor-styles-wrapper' ),
			'frame'   => 'canvas',
			'tier'    => 'recommended',
		),
		array(
			'id'           => 'c3-publish',
			'chapter'      => 3,
			'group'        => 'operator_info',
			'title'        => '公開する',
			'body'         => '公開すると、LPの「運営者情報リンク設定」で選べるようになります。<strong>公開しないと選択肢に出ません。</strong><br><strong>「公開」は2段階です</strong>。1回目で右側に確認パネルが開くので、<strong>パネル内の「公開」をもう一度押してください。</strong>',
			'target'       => $publish_target,
			'targetPanel'  => array( '.editor-post-publish-panel .editor-post-publish-button__button', '.editor-post-publish-panel .editor-post-publish-button' ),
			'tier'         => 'required',
			'awaitPublish' => true,
		),

		// ---------------- 章4: LPに戻って仕上げる ----------------
		// 商品の選択UIはサイドバー（インスペクター）ではなく、ブロック本体の中
		// （ProductOrderPicker）にある。ハイライトはキャンバス内の .lp-product-picker を狙う。
		array(
			'id'          => 'c4-comparison',
			'chapter'     => 4,
			'group'       => 'service',
			'title'       => '比較テーブルに並べる商品を選ぶ',
			'body'        => '章1で場所だけ確認したブロックです。<strong>商品名の左にあるチェックボックスをオンにすると、その商品が表の列として並びます。</strong>チェックすると下に「表示順」が出るので、↑↓ボタンで左から並ぶ順番を決められます。<br>各商品の「比較テーブルの中身」ブロックがそのまま表の行になり、最下段には各商品のCTAボタンが並びます。',
			'emptyBody'   => '選べる商品がありません。<strong>このLPを「所属LP」に指定した商品を公開する</strong>と、ここに候補として出てきます。章2で作った商品の所属LPが正しいか確認してください。',
			'target'      => array(
				'[data-type="lp-service/comparison-table"] .lp-product-picker',
				'[data-type="lp-service/comparison-table"] .lp-product-picker__empty',
				'[data-type="lp-service/comparison-table"]',
			),
			'frame'       => 'canvas',
			'selectBlock' => 'lp-service/comparison-table',
			'tier'        => 'recommended',
			'requires'    => array( 'hasProducts' ),
			'designs'     => array( 'standard' ),
		),
		array(
			'id'          => 'c4-ranking',
			'chapter'     => 4,
			'group'       => 'service',
			'title'       => '人気ランキングの商品と順位を決める',
			'body'        => 'こちらも<strong>商品名の左のチェックボックスでランキングに載せる商品を選びます。</strong>チェックすると下に「表示順」が出るので、↑↓ボタンで並べ替えてください。<strong>並べた順番がそのまま1位・2位…になります</strong>（アクセス数からの自動集計ではなく手動です）。',
			'emptyBody'   => '選べる商品がありません。<strong>このLPを「所属LP」に指定した商品を公開する</strong>と、ここに候補として出てきます。',
			'target'      => array(
				'[data-type="lp-service/ranking"] .lp-product-picker',
				'[data-type="lp-service/ranking"] .lp-product-picker__empty',
				'[data-type="lp-service/ranking"]',
			),
			'frame'       => 'canvas',
			'selectBlock' => 'lp-service/ranking',
			'tier'        => 'recommended',
			'requires'    => array( 'hasProducts' ),
			'designs'     => array( 'standard' ),
		),
		array(
			'id'        => 'c4-operator',
			'chapter'   => 4,
			'group'     => 'service',
			'title'     => '運営者情報をリンクする',
			'body'      => '章3で作ったページを選ぶと、<strong>このLPのフッターに「運営者情報」リンクが表示されます。</strong>',
			'emptyBody' => '選択肢がありません。<strong>運営者情報ページを「公開」する</strong>と、ここに出てきます（下書きのままだと選べません）。',
			'target'    => array( 'select[name="lp_operator_id"]' ),
			'expandTo'  => '#lp_service_operator .inside',
			'tier'      => 'recommended',
			'requires'  => array( 'hasOperatorInfo' ),
		),
		array(
			'id'        => 'c4-update',
			'chapter'   => 4,
			'group'     => 'service',
			'title'     => '更新する',
			'body'      => 'ここまでの変更を保存します。すでに公開済みのLPなら「更新」の1クリックで完了します（まだ公開していない場合は「公開」→確認パネルの「公開」の2段階です）。',
			'target'    => $publish_target,
			'tier'      => 'required',
			'awaitSave' => true,
		),
		array(
			'id'      => 'c4-view',
			'chapter' => 4,
			'group'   => 'service',
			'title'   => 'できたLPを見る',
			'body'    => '実際のページを別タブで開いて、比較テーブル・ランキング・絞り込みフォーム・フッターのリンクを確認してみましょう。お疲れさまでした！',
			'target'  => array( '.editor-header__settings a[href]', '.edit-post-header__settings a[href]', '#wp-admin-bar-view a' ),
			'tier'    => 'optional',
			'isFinal' => true,
		),
	);
}

/**
 * サンプル値セット。
 *
 * type: 'field'       … セレクターで引ける input・textarea に value を入れる
 *       'editorTitle' … ブロックエディタのタイトル（core/editor の title 属性）
 */
function lp_service_get_tour_samples() {
	return array(
		'lp_title'           => array(
			'label'  => 'LPのタイトル',
			'fields' => array(
				array(
					'type'  => 'editorTitle',
					'label' => 'タイトル',
					'value' => '買取比較ガイド（サンプル）',
				),
			),
		),
		'lp_catch_copy'      => array(
			'label'  => 'キャッチコピー',
			'fields' => array(
				array(
					'type'     => 'field',
					'selector' => '#lp_header_catch_copy',
					'label'    => 'キャッチコピー',
					'value'    => 'あなたにぴったりの買取業者が見つかる',
				),
			),
		),
		'filter_select1'     => array(
			'label'  => 'セレクト1',
			'fields' => array(
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_select1_label"]',
					'label'    => 'セレクト1 ラベル',
					'value'    => '対応エリア',
				),
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_select1_options"]',
					'label'    => 'セレクト1 選択肢',
					'value'    => "関東\n関西\n東海\n九州",
				),
			),
		),
		'filter_select2'     => array(
			'label'  => 'セレクト2',
			'fields' => array(
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_select2_label"]',
					'label'    => 'セレクト2 ラベル',
					'value'    => '買取方法',
				),
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_select2_options"]',
					'label'    => 'セレクト2 選択肢',
					'value'    => "出張買取\n宅配買取\n店頭買取",
				),
			),
		),
		'filter_checkboxes'  => array(
			'label'  => 'チェックボックス',
			'fields' => array(
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_checkbox1_label"]',
					'label'    => 'チェックボックス1',
					'value'    => '査定料無料',
				),
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_checkbox2_label"]',
					'label'    => 'チェックボックス2',
					'value'    => '即日対応',
				),
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_checkbox3_label"]',
					'label'    => 'チェックボックス3',
					'value'    => '全国対応',
				),
				array(
					'type'     => 'field',
					'selector' => '[name="lp_filter_checkbox4_label"]',
					'label'    => 'チェックボックス4',
					'value'    => 'キャンセル料無料',
				),
			),
		),
		'product_title'      => array(
			'label'  => '商品名',
			'fields' => array(
				array(
					'type'  => 'editorTitle',
					'label' => '商品名',
					'value' => 'サンプル買取センター',
				),
			),
		),
		'operator_title'     => array(
			'label'  => 'タイトル',
			'fields' => array(
				array(
					'type'  => 'editorTitle',
					'label' => 'タイトル',
					'value' => '運営者情報',
				),
			),
		),
		'product_catch_copy' => array(
			'label'  => 'キャッチコピー',
			'fields' => array(
				array(
					'type'     => 'field',
					'selector' => '#lp_product_catch_copy',
					'label'    => 'キャッチコピー',
					'value'    => '創業20年、累計実績10万件の安心買取',
				),
			),
		),
	);
}

/**
 * 画面ID → 案内文で使う日本語ラベル
 */
function lp_service_get_tour_screen_labels() {
	return array(
		'edit-service'       => 'LP一覧',
		'service'            => 'LP編集',
		'edit-product'       => '商品一覧',
		'product'            => '商品編集',
		'edit-operator_info' => '運営者情報一覧',
		'operator_info'      => '運営者情報編集',
	);
}

/**
 * 現在の管理画面が扱っている投稿を返す（post.php / post-new.php 共通）
 */
function lp_service_get_tour_current_post() {
	$post = get_post();
	if ( $post instanceof WP_Post ) {
		return $post;
	}

	if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = get_post( (int) $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post instanceof WP_Post ) {
			return $post;
		}
	}

	return null;
}

/**
 * 指定LPの絞り込みフォーム設定が1つでも入力されているか
 */
function lp_service_tour_filter_configured( $service_id ) {
	if ( ! $service_id || 'service' !== get_post_type( $service_id ) ) {
		return false;
	}

	$config = lp_service_get_filter_config( $service_id );

	foreach ( array( 'select1', 'select2', 'checkbox1', 'checkbox2', 'checkbox3', 'checkbox4' ) as $key ) {
		if ( isset( $config[ $key ]['label'] ) && '' !== $config[ $key ]['label'] ) {
			return true;
		}
	}

	return false;
}

/**
 * 前提条件の充足状況を算出する。JS側は requires に挙げたキーがfalseなら
 * そのステップの本文を emptyBody に差し替えて「なぜ空なのか」を説明する。
 */
function lp_service_get_tour_prerequisites( $screen_id ) {
	$prereq = array(
		'designTemplate'        => '',
		'hasDesignTemplate'     => false,
		// どのLPの話なのかが分かる画面か。LP一覧・商品一覧・運営者情報の画面では
		// 特定できないため、この値がfalseのときは「未設定」と断定してはいけない。
		'serviceContextKnown'   => false,
		'filterConfigured'      => false,
		'publishedProductCount' => 0,
		'hasProducts'           => false,
		'hasOperatorInfo'       => false,
	);

	$operators = get_posts(
		array(
			'post_type'              => 'operator_info',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$prereq['hasOperatorInfo'] = ! empty( $operators );

	$post = lp_service_get_tour_current_post();
	if ( ! $post ) {
		return $prereq;
	}

	$service_id = 0;

	if ( 'service' === $screen_id && 'service' === $post->post_type ) {
		$service_id                  = $post->ID;
		$design                      = get_post_meta( $post->ID, '_lp_design_template', true );
		$prereq['designTemplate']    = is_string( $design ) ? $design : '';
		$prereq['hasDesignTemplate'] = '' !== $prereq['designTemplate'];
	} elseif ( 'product' === $screen_id && 'product' === $post->post_type ) {
		$service_id = (int) get_post_meta( $post->ID, '_lp_service_id', true );
	}

	if ( $service_id ) {
		$prereq['serviceContextKnown'] = true;
		$prereq['filterConfigured']    = lp_service_tour_filter_configured( $service_id );

		$products = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_lp_service_id',
						'value' => $service_id,
					),
				),
			)
		);

		$prereq['publishedProductCount'] = count( $products );
		$prereq['hasProducts']           = $prereq['publishedProductCount'] > 0;
	}

	return $prereq;
}

/**
 * 管理バーにツアー起動ボタン＋章ごとのサブメニューを追加
 */
function lp_service_tour_admin_bar( $wp_admin_bar ) {
	if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'lp-service-tour',
			'title' => '🧩 使い方ガイド',
			'href'  => '#lpt-start',
			'meta'  => array( 'class' => 'lp-service-tour-trigger' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => 'lp-service-tour',
			'id'     => 'lp-service-tour-all',
			'title'  => '最初から通しで見る',
			'href'   => '#lpt-start',
		)
	);

	foreach ( lp_service_get_tour_chapters() as $number => $chapter ) {
		$wp_admin_bar->add_node(
			array(
				'parent' => 'lp-service-tour',
				'id'     => 'lp-service-tour-chapter-' . $number,
				'title'  => '章' . $number . '：' . $chapter['title'],
				'href'   => '#lpt-chapter-' . $number,
			)
		);
	}
}
add_action( 'admin_bar_menu', 'lp_service_tour_admin_bar', 90 );

/**
 * ツアー用スクリプト・スタイルの読み込みとステップデータの受け渡し
 */
function lp_service_enqueue_tour_assets() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_style(
		'lp-service-onboarding-tour',
		LP_SERVICE_URI . '/assets/css/onboarding-tour.css',
		array(),
		LP_SERVICE_VERSION
	);

	wp_enqueue_script(
		'lp-service-onboarding-tour',
		LP_SERVICE_URI . '/assets/js/onboarding-tour.js',
		array(),
		LP_SERVICE_VERSION,
		true
	);

	$screen    = get_current_screen();
	$screen_id = $screen ? $screen->id : '';

	wp_localize_script(
		'lp-service-onboarding-tour',
		'lpServiceTourData',
		array(
			'screenId'      => $screen_id,
			'screenLabels'  => lp_service_get_tour_screen_labels(),
			'steps'         => lp_service_get_tour_steps(),
			'chapters'      => lp_service_get_tour_chapters(),
			'outros'        => lp_service_get_tour_chapter_outros(),
			'samples'       => lp_service_get_tour_samples(),
			'prerequisites' => lp_service_get_tour_prerequisites( $screen_id ),
			'urls'          => array(
				'edit-service'       => admin_url( 'edit.php?post_type=service' ),
				'edit-product'       => admin_url( 'edit.php?post_type=product' ),
				'edit-operator_info' => admin_url( 'edit.php?post_type=operator_info' ),
				'newProduct'         => admin_url( 'post-new.php?post_type=product' ),
				'editPost'           => admin_url( 'post.php?action=edit&post=' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'lp_service_enqueue_tour_assets' );
