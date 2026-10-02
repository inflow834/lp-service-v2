import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { listType, markerColor, items } = attributes;
	const blockProps = useBlockProps.save( {
		className: `lp-mark-list lp-mark-list--${ listType }`,
		style: markerColor ? { '--lp-list-marker-color': markerColor } : undefined,
	} );

	return (
		<ul { ...blockProps }>
			{ items.map( ( item, index ) => (
				<RichText.Content key={ index } tagName="li" value={ item.content } />
			) ) }
		</ul>
	);
}
