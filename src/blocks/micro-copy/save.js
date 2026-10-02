import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text, color } = attributes;
	const blockProps = useBlockProps.save( {
		className: 'lp-micro-copy',
		style: { color },
	} );

	return (
		<div { ...blockProps }>
			<RichText.Content tagName="p" className="lp-micro-copy__text" value={ text } />
		</div>
	);
}
