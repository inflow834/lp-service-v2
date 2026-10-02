/**
 * LP編集画面の「デザインテンプレート」セレクトを切り替えたとき、
 * 対応するデザインの初期ブロックをブロックエディタへ配置する。
 * 本文に既にブロックがある場合は、上書きされる旨を確認ダイアログで警告し、
 * ユーザーが承認した場合のみ本文を新しいデザインのブロックで置き換える。
 * キャンセルした場合はセレクトの表示を元の値に戻す。
 *
 * あわせて、選択中のデザインを lp-design-{slug} クラスとしてエディタキャンバスの<body>に付ける。
 * スマホ専用デザインはこのクラスを起点にキャンバスを430px幅・専用配色で表示する（assets/css/design-mobile.css）。
 */
( function () {
	var DESIGN_CLASS_PREFIX = 'lp-design-';
	var currentDesign = '';

	function createBlocksFromTemplate( template ) {
		return template.map( function ( entry ) {
			return wp.blocks.createBlock( entry[ 0 ], entry[ 1 ] || {} );
		} );
	}

	// キャンバスはiframe（通常）か、iframe化されていない場合は .editor-styles-wrapper の要素
	function getCanvasRoot() {
		var iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
		if ( iframe ) {
			return iframe.contentDocument && iframe.contentDocument.body;
		}
		return document.querySelector( '.editor-styles-wrapper' );
	}

	// iframeは後から生成・再生成されるため、エディタの状態が変わるたびに呼んで同期する
	function syncDesignClass() {
		var root = getCanvasRoot();
		if ( ! root ) {
			return;
		}

		var wanted = currentDesign ? DESIGN_CLASS_PREFIX + currentDesign : '';
		Array.prototype.slice.call( root.classList ).forEach( function ( className ) {
			if ( className.indexOf( DESIGN_CLASS_PREFIX ) === 0 && className !== wanted ) {
				root.classList.remove( className );
			}
		} );
		if ( wanted && ! root.classList.contains( wanted ) ) {
			root.classList.add( wanted );
		}
	}

	function setCurrentDesign( design ) {
		currentDesign = design;
		syncDesignClass();
	}

	function handleChange( event ) {
		var select = event.target;
		var design = select.value;
		var previousValue = select.dataset.lpPreviousValue || '';

		if ( design === previousValue ) {
			return;
		}

		var templates = window.lpServiceDesignBlockTemplates || {};
		var template = templates[ design ];

		// 「デザインを選択してください」など、初期ブロックを持たない選択肢は本文に触れない。
		if ( ! template || ! window.wp || ! wp.data || ! wp.blocks ) {
			select.dataset.lpPreviousValue = design;
			setCurrentDesign( design );
			return;
		}

		var blockEditor = wp.data.select( 'core/block-editor' );
		var hasBlocks = !! ( blockEditor && blockEditor.getBlocks().length > 0 );

		if ( hasBlocks ) {
			var confirmed = window.confirm(
				'デザインを切り替えると、現在本文に配置されているブロックはすべて削除され、新しいデザインの初期ブロックに置き換わります。よろしいですか？'
			);
			if ( ! confirmed ) {
				select.value = previousValue;
				return;
			}
		}

		wp.data.dispatch( 'core/block-editor' ).resetBlocks( createBlocksFromTemplate( template ) );
		select.dataset.lpPreviousValue = design;
		setCurrentDesign( design );
	}

	function init() {
		var select = document.getElementById( 'lp_design_template' );
		if ( ! select ) {
			return;
		}
		select.dataset.lpPreviousValue = select.value;
		select.addEventListener( 'change', handleChange );

		setCurrentDesign( select.value );
		if ( window.wp && wp.data ) {
			wp.data.subscribe( syncDesignClass );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
