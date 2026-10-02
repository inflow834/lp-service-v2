import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save( { className: 'lp-block-free-section' } );
	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
