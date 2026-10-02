import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { heading } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-points' } );

	return (
		<div { ...blockProps }>
			{ heading && <RichText.Content tagName="h2" className="lp-points__heading" value={ heading } /> }
			<div className="lp-points__list">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}
