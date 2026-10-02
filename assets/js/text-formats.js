/**
 * ブロックエディタのテキスト装飾（黄色マーカー・赤文字・青文字）
 * RichTextの書式として登録する。選択したテキストをツールバーのボタンで装飾でき、
 * 同じボタンをもう一度押すと解除される。見た目は assets/css/text-formats.css。
 * 登録するだけなので、allowedFormatsを絞っていないRichText（説明文・コアの段落など）では自動で使える。
 * ビルド不要の素のJS（design-template-switcher.js と同じ扱い）。
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var registerFormatType = wp.richText.registerFormatType;
	var toggleFormat = wp.richText.toggleFormat;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;

	var FORMATS = [
		{
			name: 'lp-service/text-marker',
			className: 'lp-text-marker',
			title: '黄色マーカー',
			iconStyle: { background: 'linear-gradient(transparent 55%, #fff200 55%)' },
		},
		{
			name: 'lp-service/text-red',
			className: 'lp-text-red',
			title: '赤文字',
			iconStyle: { color: '#e60012' },
		},
		{
			name: 'lp-service/text-blue',
			className: 'lp-text-blue',
			title: '青文字',
			iconStyle: { color: '#0068b7' },
		},
	];

	FORMATS.forEach( function ( format ) {
		var icon = el( 'span', { style: Object.assign( { fontWeight: 700, fontSize: '16px', lineHeight: 1 }, format.iconStyle ) }, 'A' );

		registerFormatType( format.name, {
			title: format.title,
			tagName: 'span',
			className: format.className,
			edit: function ( props ) {
				return el( RichTextToolbarButton, {
					icon: icon,
					title: format.title,
					isActive: props.isActive,
					onClick: function () {
						props.onChange( toggleFormat( props.value, { type: format.name } ) );
					},
				} );
			},
		} );
	} );
} )( window.wp );
