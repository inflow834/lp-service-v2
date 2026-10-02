import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { imageUrl } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-simple-banner' } );

	if ( ! imageUrl ) {
		return null;
	}

	return (
		<div { ...blockProps }>
			<img src={ imageUrl } alt="" />
		</div>
	);
}
