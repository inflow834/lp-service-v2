import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { heading, body, imageUrl } = attributes;
	const blockProps = useBlockProps.save( { className: 'lp-points__item' } );

	return (
		<div { ...blockProps }>
			{ imageUrl && (
				<div className="lp-points__item-image">
					<img src={ imageUrl } alt="" />
				</div>
			) }
			<div className="lp-points__content">
				<div className="lp-points__content-head">
					<span className="lp-points__number" aria-hidden="true"></span>
					{ heading && <RichText.Content tagName="h3" className="lp-points__item-heading" value={ heading } /> }
				</div>
				{ body && <RichText.Content tagName="p" className="lp-points__body" value={ body } /> }
			</div>
		</div>
	);
}
