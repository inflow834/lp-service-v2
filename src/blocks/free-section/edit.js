import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_BLOCKS = [
	'core/heading',
	'core/paragraph',
	'core/image',
	'core/list',
	'core/buttons',
	'core/columns',
	'core/group',
	'core/spacer',
	'core/separator',
];

const TEMPLATE = [
	[ 'core/heading', { level: 2, placeholder: '見出しを入力' } ],
	[ 'core/paragraph', { placeholder: '本文を入力' } ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'lp-block-free-section' } );
	return (
		<div { ...blockProps }>
			<InnerBlocks allowedBlocks={ ALLOWED_BLOCKS } template={ TEMPLATE } templateInsertUpdatesSelection={ false } />
		</div>
	);
}
