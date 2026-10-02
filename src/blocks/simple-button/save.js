import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text, url, color, textColor } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-simple-button' } );

	return (
		<div { ...blockProps }>
			<a
				className="lp-simple-button__link"
				href={ url || '#' }
				style={ { backgroundColor: color, color: textColor } }
			>
				<span className="lp-simple-button__arrow" aria-hidden="true">▶</span>
				<RichText.Content tagName="span" value={ text } />
			</a>
		</div>
	);
}
