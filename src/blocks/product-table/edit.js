import { useBlockProps } from '@wordpress/block-editor';
import { TextControl, ButtonGroup, Button } from '@wordpress/components';
import { TABLE_ICONS } from './icons';

export default function Edit( { attributes, setAttributes } ) {
	const { items } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-product-table' } );

	const updateItem = ( index, key, value ) => {
		const next = items.map( ( item, i ) => ( i === index ? { ...item, [ key ]: value } : item ) );
		setAttributes( { items: next } );
	};

	return (
		<div { ...blockProps }>
			<p className="lp-block-product-table__hint">3列 × 2行・6項目（自由入力）</p>
			<div className="lp-block-product-table__grid">
				{ items.map( ( item, index ) => (
					<div className="lp-block-product-table__cell" key={ index }>
						<TextControl
							label={ `項目${ index + 1 } ラベル` }
							value={ item.label }
							onChange={ ( value ) => updateItem( index, 'label', value ) }
						/>
						<div className="lp-block-product-table__icon-picker">
							<ButtonGroup>
								{ Object.entries( TABLE_ICONS ).map( ( [ key, icon ] ) => (
									<Button
										key={ key }
										isPressed={ item.icon === key }
										onClick={ () => updateItem( index, 'icon', key ) }
										label={ icon.label }
									>
										<span className="lp-block-product-table__icon">{ icon.svg }</span>
									</Button>
								) ) }
							</ButtonGroup>
						</div>
						<TextControl
							label="アイコン下のテキスト"
							value={ item.caption }
							onChange={ ( value ) => updateItem( index, 'caption', value ) }
						/>
					</div>
				) ) }
			</div>
		</div>
	);
}
