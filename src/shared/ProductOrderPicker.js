import { CheckboxControl, Button } from '@wordpress/components';
import { arrowUp, arrowDown } from '@wordpress/icons';
import useServiceProducts from './useServiceProducts';

/**
 * 現在のLPに属する商品から複数選択し、選択済みは並び替えできるピッカー
 *
 * @param {number[]} selectedIds
 * @param {Function} onChange
 * @param {number}   max        選択可能な最大数（0で無制限）
 */
export default function ProductOrderPicker( { selectedIds, onChange, max = 0 } ) {
	const products = useServiceProducts();

	if ( ! products.length ) {
		return <p className="lp-product-picker__empty">このLPに紐づく商品がまだありません。先に商品を作成し「所属LP」を設定してください。</p>;
	}

	const toggle = ( id ) => {
		if ( selectedIds.includes( id ) ) {
			onChange( selectedIds.filter( ( existing ) => existing !== id ) );
		} else {
			if ( max && selectedIds.length >= max ) {
				return;
			}
			onChange( [ ...selectedIds, id ] );
		}
	};

	const move = ( index, direction ) => {
		const next = [ ...selectedIds ];
		const target = index + direction;
		if ( target < 0 || target >= next.length ) {
			return;
		}
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	const titleById = ( id ) => {
		const product = products.find( ( p ) => p.id === id );
		return product ? ( product.title?.rendered || '(無題)' ) : `#${ id }`;
	};

	return (
		<div className="lp-product-picker">
			<div className="lp-product-picker__list">
				{ products.map( ( product ) => (
					<CheckboxControl
						key={ product.id }
						label={ product.title?.rendered || '(無題)' }
						checked={ selectedIds.includes( product.id ) }
						onChange={ () => toggle( product.id ) }
					/>
				) ) }
			</div>

			{ selectedIds.length > 0 && (
				<>
					<p className="lp-product-picker__order-label">表示順（上から順に表示）</p>
					<ol className="lp-product-picker__order-list">
						{ selectedIds.map( ( id, index ) => (
							<li key={ id } className="lp-product-picker__order-item">
								<span className="lp-product-picker__order-rank">{ index + 1 }</span>
								<span className="lp-product-picker__order-title">{ titleById( id ) }</span>
								<Button icon={ arrowUp } label="上へ" onClick={ () => move( index, -1 ) } disabled={ index === 0 } isSmall />
								<Button icon={ arrowDown } label="下へ" onClick={ () => move( index, 1 ) } disabled={ index === selectedIds.length - 1 } isSmall />
							</li>
						) ) }
					</ol>
				</>
			) }
		</div>
	);
}
