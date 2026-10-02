import { useBlockProps } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import ProductOrderPicker from '../../shared/ProductOrderPicker';

export default function Edit( { attributes, setAttributes } ) {
	const { title, productIds } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-comparison-table' } );

	return (
		<div { ...blockProps }>
			<TextControl label="セクション見出し" value={ title } onChange={ ( value ) => setAttributes( { title: value } ) } />
			<p className="lp-block-comparison-table__hint">比較表示する商品を選択してください（横軸に並びます）</p>
			<ProductOrderPicker selectedIds={ productIds } onChange={ ( ids ) => setAttributes( { productIds: ids } ) } />
		</div>
	);
}
