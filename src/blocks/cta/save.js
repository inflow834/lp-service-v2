import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const {
		heading,
		imageUrl,
		catchCopy,
		freeText,
		buttonText,
		buttonUrl,
		buttonColor,
		buttonTextColor,
	} = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-cta' } );

	return (
		<div { ...blockProps }>
			{ heading && <RichText.Content tagName="h2" className="lp-cta__heading" value={ heading } /> }
			{ imageUrl && (
				<div className="lp-cta__image">
					<img src={ imageUrl } alt="" />
				</div>
			) }
			{ catchCopy && <p className="lp-cta__catch">{ catchCopy }</p> }
			<div className="lp-cta__button-wrap">
				<a className="lp-cta__button" href={ buttonUrl || '#' } style={ { backgroundColor: buttonColor, color: buttonTextColor } }>
					<RichText.Content tagName="span" value={ buttonText } />
				</a>
			</div>
			{ freeText && <RichText.Content tagName="div" className="lp-cta__free-text" value={ freeText } /> }
		</div>
	);
}
