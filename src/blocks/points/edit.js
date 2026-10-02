import { useBlockProps, InnerBlocks, RichText } from '@wordpress/block-editor';

const ALLOWED_BLOCKS = [ 'lp-service/points-item' ];
const TEMPLATE = [
	[ 'lp-service/points-item', {} ],
	[ 'lp-service/points-item', {} ],
];

export default function Edit( { attributes, setAttributes } ) {
	const { heading } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-points' } );

	return (
		<div { ...blockProps }>
			<RichText tagName="h2" className="lp-block-points__heading" value={ heading } onChange={ ( v ) => setAttributes( { heading: v } ) } placeholder="セクション見出し" allowedFormats={ [] } />
			<InnerBlocks allowedBlocks={ ALLOWED_BLOCKS } template={ TEMPLATE } />
		</div>
	);
}
