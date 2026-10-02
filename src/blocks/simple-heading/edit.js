import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const { text } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-simple-heading' } );

	return (
		<div { ...blockProps }>
			<RichText
				tagName="h2"
				className="lp-block-simple-heading__text"
				value={ text }
				onChange={ ( value ) => setAttributes( { text: value } ) }
				placeholder="見出しを入力"
				allowedFormats={ [] }
			/>
		</div>
	);
}
