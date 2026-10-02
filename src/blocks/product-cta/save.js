import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text, url, color, textColor } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-product-cta' } );

	return (
		<div { ...blockProps }>
			<a
				className="lp-product-cta__button"
				href={ url || '#' }
				style={ { backgroundColor: color, color: textColor } }
			>
				<RichText.Content tagName="span" value={ text } />
			</a>
		</div>
	);
}
