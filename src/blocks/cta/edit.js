import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
	PanelColorSettings,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { TextControl, Button, Popover } from '@wordpress/components';
import { useState } from '@wordpress/element';

const BUTTON_COLORS = [
	{ name: 'レッド', color: '#d63638' },
	{ name: 'グリーン', color: '#2fa84f' },
	{ name: 'オレンジ', color: '#f5a623' },
	{ name: 'ブルー', color: '#2271b1' },
	{ name: 'ダーク', color: '#1a1a1a' },
];

const TEXT_COLORS = [
	{ name: '白', color: '#ffffff' },
	{ name: '黒', color: '#1a1a1a' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		heading,
		imageId,
		imageUrl,
		catchCopy,
		freeText,
		buttonText,
		buttonUrl,
		buttonColor,
		buttonTextColor,
	} = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-cta' } );
	const [ isLinkOpen, setIsLinkOpen ] = useState( false );

	return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title="ボタンの色"
					initialOpen
					colorSettings={ [
						{
							value: buttonColor,
							onChange: ( value ) => setAttributes( { buttonColor: value || '#d63638' } ),
							label: '背景色',
							colors: BUTTON_COLORS,
						},
						{
							value: buttonTextColor,
							onChange: ( value ) => setAttributes( { buttonTextColor: value || '#ffffff' } ),
							label: '文字色',
							colors: TEXT_COLORS,
						},
					] }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText tagName="h2" className="lp-block-cta__heading" value={ heading } onChange={ ( v ) => setAttributes( { heading: v } ) } placeholder="見出しを入力" allowedFormats={ [] } />

				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ) }
						allowedTypes={ [ 'image' ] }
						value={ imageId }
						render={ ( { open } ) => (
							<Button onClick={ open } variant="secondary" className="lp-block-cta__image-button">
								{ imageUrl ? <img src={ imageUrl } alt="" /> : '画像を選択' }
							</Button>
						) }
					/>
				</MediaUploadCheck>

				<TextControl label="キャッチコピー" value={ catchCopy } onChange={ ( v ) => setAttributes( { catchCopy: v } ) } />

				<div className="lp-block-cta__button-wrap">
					<RichText
						tagName="span"
						className="lp-block-cta__button"
						style={ { backgroundColor: buttonColor, color: buttonTextColor } }
						value={ buttonText }
						onChange={ ( v ) => setAttributes( { buttonText: v } ) }
						allowedFormats={ [] }
					/>
					<div className="lp-block-cta__link-control">
						<Button variant="link" onClick={ () => setIsLinkOpen( ( v ) => ! v ) }>
							{ buttonUrl ? `リンク: ${ buttonUrl }` : 'リンクを設定' }
						</Button>
						{ isLinkOpen && (
							<Popover onClose={ () => setIsLinkOpen( false ) }>
								<LinkControl value={ { url: buttonUrl } } onChange={ ( v ) => setAttributes( { buttonUrl: v.url || '' } ) } />
							</Popover>
						) }
					</div>
				</div>

				<RichText tagName="p" className="lp-block-cta__free-text" value={ freeText } onChange={ ( v ) => setAttributes( { freeText: v } ) } placeholder="自由入力テキスト" />
			</div>
		</>
	);
}
