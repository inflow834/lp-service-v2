import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

/**
 * 編集中のLP(service)に紐づく商品一覧を取得するフック
 */
export default function useServiceProducts() {
	const serviceId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );

	const products = useSelect(
		( select ) => {
			if ( ! serviceId ) {
				return [];
			}
			const records = select( 'core' ).getEntityRecords( 'postType', 'product', {
				service_id: serviceId,
				per_page: -1,
				status: 'any',
				_fields: [ 'id', 'title' ],
			} );
			return records || [];
		},
		[ serviceId ]
	);

	return useMemo( () => products, [ products ] );
}
