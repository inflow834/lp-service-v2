import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const { text } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-simple-description' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="p"
				value={ text }
				onChange={ ( value ) => setAttributes( { text: value } ) }
				placeholder="説明文を入力"
			/>
		</div>
	);
}
