import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-simple-heading' } );

	return (
		<div { ...blockProps }>
			<RichText.Content tagName="h2" className="lp-simple-heading__text" value={ text } />
		</div>
	);
}
