import { useBlockProps, RichText, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';

const TEXT_COLORS = [
	{ name: 'レッド', color: '#e6393f' },
	{ name: 'オレンジ', color: '#ff7a1a' },
	{ name: 'ブルー', color: '#2271b1' },
	{ name: 'ネイビー', color: '#1f3a5f' },
	{ name: 'ダーク', color: '#1a1a1a' },
];

export default function Edit( { attributes, setAttributes } ) {
	const { text, color } = attributes;
	const blockProps = useBlockProps( {
		className: 'lp-micro-copy',
		style: { color },
	} );

	return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title="文字色"
					initialOpen
					colorSettings={ [
						{
							value: color,
							onChange: ( value ) => setAttributes( { color: value || '#e6393f' } ),
							label: '文字色（両側の斜線も同じ色）',
							colors: TEXT_COLORS,
						},
					] }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="p"
					className="lp-micro-copy__text"
					value={ text }
					onChange={ ( value ) => setAttributes( { text: value } ) }
					placeholder="今がおとく"
					allowedFormats={ [ 'core/bold', 'lp-service/text-red', 'lp-service/text-blue', 'lp-service/text-marker' ] }
				/>
			</div>
		</>
	);
}
