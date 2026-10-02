import { useBlockProps } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import ProductOrderPicker from '../../shared/ProductOrderPicker';

export default function Edit( { attributes, setAttributes } ) {
	const { title, productIds } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-ranking' } );

	return (
		<div { ...blockProps }>
			<TextControl label="セクション見出し" value={ title } onChange={ ( value ) => setAttributes( { title: value } ) } />
			<p className="lp-block-ranking__hint">ランキングに表示する商品を選択し、順位（上から1位）を並び替えてください</p>
			<ProductOrderPicker selectedIds={ productIds } onChange={ ( ids ) => setAttributes( { productIds: ids } ) } />
		</div>
	);
}
