import { useBlockProps, RichText, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Button } from '@wordpress/components';
import { useRef, useEffect, useState } from '@wordpress/element';
import { plus } from '@wordpress/icons';

const LIST_TYPES = [
	{ label: '丸', value: 'circle' },
	{ label: '四角', value: 'square' },
	{ label: 'チェックマーク', value: 'check' },
];

const MARKER_COLORS = [
	{ name: 'オレンジ', color: '#f5a623' },
	{ name: 'レッド', color: '#e6393f' },
	{ name: 'グリーン', color: '#2fa84f' },
	{ name: 'ブルー', color: '#2271b1' },
	{ name: 'ネイビー', color: '#1f3a5f' },
];

export default function Edit( { attributes, setAttributes } ) {
	const { listType, markerColor, items } = attributes;
	const blockProps = useBlockProps( {
		className: `lp-mark-list lp-mark-list--${ listType }`,
		style: markerColor ? { '--lp-list-marker-color': markerColor } : undefined,
	} );

	// 項目の追加・削除の直後に、フォーカスを移したい項目の番号を持っておく
	const itemRefs = useRef( [] );
	const [ focusIndex, setFocusIndex ] = useState( null );

	useEffect( () => {
		if ( focusIndex !== null && itemRefs.current[ focusIndex ] ) {
			itemRefs.current[ focusIndex ].focus();
			setFocusIndex( null );
		}
	}, [ focusIndex, items.length ] );

	const updateItem = ( index, content ) => {
		setAttributes( { items: items.map( ( item, i ) => ( i === index ? { content } : item ) ) } );
	};

	const addItemAfter = ( index ) => {
		const next = [ ...items ];
		next.splice( index + 1, 0, { content: '' } );
		setAttributes( { items: next } );
		setFocusIndex( index + 1 );
	};

	const removeItem = ( index ) => {
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
		setFocusIndex( Math.max( index - 1, 0 ) );
	};

	// Enterで次の項目を追加、空の項目でBackspaceするとその項目を削除（コアのリストブロックと同じ操作感）
	const handleKeyDown = ( event, index ) => {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			addItemAfter( index );
		} else if ( event.key === 'Backspace' && items[ index ].content === '' && items.length > 1 ) {
			event.preventDefault();
			removeItem( index );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title="リストの種類" initialOpen>
					<SelectControl
						label="先頭のマーク"
						value={ listType }
						options={ LIST_TYPES }
						onChange={ ( value ) => setAttributes( { listType: value } ) }
					/>
				</PanelBody>
				<PanelColorSettings
					title="マークの色"
					initialOpen
					colorSettings={ [
						{
							value: markerColor,
							onChange: ( value ) => setAttributes( { markerColor: value || '' } ),
							label: 'マークの色（未選択ならデザインの色）',
							colors: MARKER_COLORS,
						},
					] }
				/>
			</InspectorControls>
			<ul { ...blockProps }>
				{ items.map( ( item, index ) => (
					<li key={ index }>
						<RichText
							tagName="span"
							ref={ ( node ) => ( itemRefs.current[ index ] = node ) }
							value={ item.content }
							onChange={ ( content ) => updateItem( index, content ) }
							onKeyDown={ ( event ) => handleKeyDown( event, index ) }
							placeholder="リスト項目を入力（Enterで次の項目）"
							allowedFormats={ [ 'core/bold', 'lp-service/text-red', 'lp-service/text-blue', 'lp-service/text-marker' ] }
						/>
					</li>
				) ) }
			</ul>
			<Button icon={ plus } variant="secondary" onClick={ () => addItemAfter( items.length - 1 ) }>
				項目を追加
			</Button>
		</>
	);
}
