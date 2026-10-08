import { PanelBody, ToggleControl, SelectControl, TextareaControl, ExternalLink } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

// クッションページを使えるボタンブロック（inc/cushion.php の lp_service_cushion_blocks() と揃える）
const CUSHION_BLOCKS = [ 'lp-service/product-cta', 'lp-service/cta', 'lp-service/simple-button' ];

/**
 * ボタンのサイドバーに出す「クッションページ」設定
 *
 * ボタン番号（buttonNo）はクッションページURL /go/{投稿ID}/{ボタン番号}/ の末尾。
 * ONにしたときに投稿内のボタンで未使用の番号を自動で振り、ブロックの複製などで
 * 番号が重複した場合は後ろにあるほうを振り直す。
 */
export default function CushionPanel( { clientId, attributes, setAttributes } ) {
	const { cushion = false, buttonNo = 0, cvTagId = 0, cvTagCustom = '' } = attributes;
	const data = window.lpServiceCushion || { tags: [], canUnfilteredHtml: false, canManageTags: false };

	const { postId, numbers } = useSelect( ( select ) => {
		const { getClientIdsWithDescendants, getBlockName, getBlockAttributes } = select( 'core/block-editor' );
		// 毎回新しい配列を返すと再描画が止まらないため、文字列にまとめて返す
		const list = getClientIdsWithDescendants()
			.filter( ( id ) => CUSHION_BLOCKS.includes( getBlockName( id ) ) )
			.map( ( id ) => `${ id }:${ getBlockAttributes( id )?.buttonNo || 0 }` )
			.join( ',' );
		return { postId: select( 'core/editor' ).getCurrentPostId(), numbers: list };
	}, [] );

	useEffect( () => {
		if ( ! cushion ) {
			return;
		}
		const list = numbers
			.split( ',' )
			.filter( Boolean )
			.map( ( item ) => {
				const [ id, no ] = item.split( ':' );
				return { id, no: Number( no ) };
			} );
		const myIndex = list.findIndex( ( item ) => item.id === clientId );
		const duplicated = list.slice( 0, myIndex ).some( ( item ) => item.no === buttonNo );
		if ( buttonNo > 0 && ! duplicated ) {
			return;
		}
		const max = list.reduce( ( acc, item ) => Math.max( acc, item.no ), 0 );
		setAttributes( { buttonNo: max + 1 } );
	}, [ cushion, buttonNo, numbers, clientId ] );

	const tagOptions = [
		{ label: '選択しない', value: 0 },
		...data.tags.map( ( tag ) => ( { label: `${ tag.title }（ID: ${ tag.id }）`, value: tag.id } ) ),
	];

	return (
		<PanelBody title="クッションページ" initialOpen={ cushion }>
			<ToggleControl
				label="クッションページを使う"
				help="ONにすると、ボタンを押したときにタグを読み込むページを経由してからリンク先へ移動します。"
				checked={ cushion }
				onChange={ ( value ) => setAttributes( { cushion: value } ) }
			/>
			{ cushion && (
				<>
					<SelectControl
						label="タグ"
						value={ cvTagId }
						options={ tagOptions }
						onChange={ ( value ) => setAttributes( { cvTagId: Number( value ) } ) }
					/>
					{ data.canManageTags && (
						<p>
							<ExternalLink href={ data.tagsAdminUrl }>タグを登録・編集する</ExternalLink>
						</p>
					) }
					{ data.canUnfilteredHtml ? (
						<TextareaControl
							label="個別タグ"
							help="このボタンだけで使うタグを貼り付けます。入力した場合は、上で選んだタグの代わりにこちらを読み込みます。"
							value={ cvTagCustom }
							onChange={ ( value ) => setAttributes( { cvTagCustom: value } ) }
							rows={ 6 }
						/>
					) : (
						cvTagCustom && <p>個別タグが設定されています（編集は管理者のみ）。</p>
					) }
					{ buttonNo > 0 && postId && (
						<p style={ { fontSize: '12px', color: '#757575', wordBreak: 'break-all' } }>
							クッションページURL（保存後に有効）:
							<br />
							{ `${ data.cushionBaseUrl }${ postId }/${ buttonNo }/` }
						</p>
					) }
				</>
			) }
		</PanelBody>
	);
}
