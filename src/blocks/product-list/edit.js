import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, SelectControl, Button, PanelBody, Icon } from '@wordpress/components';
import { plus, trash, check, arrowRight } from '@wordpress/icons';

const ICON_OPTIONS = [
	{ label: 'チェックマーク', value: 'check' },
	{ label: '矢印', value: 'arrow' },
	{ label: 'ドット', value: 'dot' },
];

const ICON_PREVIEW = {
	check,
	arrow: arrowRight,
	dot: 'marker',
};

export default function Edit( { attributes, setAttributes } ) {
	const { heading, iconType, items } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-product-list' } );

	const updateItem = ( index, value ) => {
		const next = [ ...items ];
		next[ index ] = value;
		setAttributes( { items: next } );
	};

	const addItem = () => setAttributes( { items: [ ...items, '' ] } );
	const removeItem = ( index ) => setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );

	return (
		<>
			<InspectorControls>
				<PanelBody title="リスト設定">
					<SelectControl label="アイコンの種類" value={ iconType } options={ ICON_OPTIONS } onChange={ ( value ) => setAttributes( { iconType: value } ) } />
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<TextControl label="見出し" value={ heading } onChange={ ( value ) => setAttributes( { heading: value } ) } />
				<ul className="lp-block-product-list__items">
					{ items.map( ( item, index ) => (
						<li key={ index } className="lp-block-product-list__item">
							<Icon icon={ ICON_PREVIEW[ iconType ] } />
							<TextControl value={ item } onChange={ ( value ) => updateItem( index, value ) } placeholder="リスト項目を入力" />
							<Button icon={ trash } label="削除" onClick={ () => removeItem( index ) } isSmall />
						</li>
					) ) }
				</ul>
				<Button icon={ plus } variant="secondary" onClick={ addItem }>
					項目を追加
				</Button>
			</div>
		</>
	);
}
