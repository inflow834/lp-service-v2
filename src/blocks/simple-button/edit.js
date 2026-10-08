import {
	useBlockProps,
	RichText,
	InspectorControls,
	PanelColorSettings,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { Popover, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import CushionPanel from '../../shared/CushionPanel';
import NewTabPanel from '../../shared/NewTabPanel';

const BUTTON_COLORS = [
	{ name: 'レッド', color: '#e6393f' },
	{ name: 'グリーン', color: '#2fa84f' },
	{ name: 'オレンジ', color: '#f5a623' },
	{ name: 'ブルー', color: '#2271b1' },
	{ name: 'ダーク', color: '#1a1a1a' },
];

const TEXT_COLORS = [
	{ name: '白', color: '#ffffff' },
	{ name: '黒', color: '#1a1a1a' },
];

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { text, url, color, textColor } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-simple-button' } );
	const [ isLinkOpen, setIsLinkOpen ] = useState( false );

	return (
		<>
			<InspectorControls>
				<PanelColorSettings
					title="ボタンの色"
					initialOpen
					colorSettings={ [
						{
							value: color,
							onChange: ( value ) => setAttributes( { color: value || '#e6393f' } ),
							label: '背景色',
							colors: BUTTON_COLORS,
						},
						{
							value: textColor,
							onChange: ( value ) => setAttributes( { textColor: value || '#ffffff' } ),
							label: '文字色',
							colors: TEXT_COLORS,
						},
					] }
				/>
				<NewTabPanel attributes={ attributes } setAttributes={ setAttributes } />
				<CushionPanel clientId={ clientId } attributes={ attributes } setAttributes={ setAttributes } />
			</InspectorControls>
			<div { ...blockProps }>
				<div className="lp-block-simple-button__button-wrap">
					<span className="lp-block-simple-button__button" style={ { backgroundColor: color, color: textColor } }>
						<span className="lp-block-simple-button__arrow" aria-hidden="true">▶</span>
						<RichText
							tagName="span"
							value={ text }
							onChange={ ( value ) => setAttributes( { text: value } ) }
							allowedFormats={ [] }
						/>
					</span>
					<div className="lp-block-simple-button__link-control">
						<Button variant="link" onClick={ () => setIsLinkOpen( ( v ) => ! v ) }>
							{ url ? `リンク: ${ url }` : 'リンクを設定' }
						</Button>
						{ isLinkOpen && (
							<Popover onClose={ () => setIsLinkOpen( false ) }>
								<LinkControl
									value={ { url, opensInNewTab: attributes.openInNewTab } }
									onChange={ ( value ) => setAttributes( { url: value.url || '', openInNewTab: value.opensInNewTab ?? attributes.openInNewTab } ) }
								/>
							</Popover>
						) }
					</div>
				</div>
			</div>
		</>
	);
}
