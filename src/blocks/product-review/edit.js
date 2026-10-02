import { useBlockProps, MediaUpload, MediaUploadCheck, RichText } from '@wordpress/block-editor';
import { TextControl, Button, ButtonGroup } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { avatarId, avatarUrl, title, rating, comment } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-product-review' } );

	return (
		<div { ...blockProps }>
			<div className="lp-block-product-review__avatar">
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) => setAttributes( { avatarId: media.id, avatarUrl: media.url } ) }
						allowedTypes={ [ 'image' ] }
						value={ avatarId }
						render={ ( { open } ) => (
							<Button onClick={ open } variant="secondary">
								{ avatarUrl ? <img src={ avatarUrl } alt="" /> : 'アイコン画像を選択' }
							</Button>
						) }
					/>
				</MediaUploadCheck>
			</div>
			<TextControl label="タイトル" value={ title } onChange={ ( value ) => setAttributes( { title: value } ) } />
			<div className="lp-block-product-review__rating">
				<span>星評価: </span>
				<ButtonGroup>
					{ [ 1, 2, 3, 4, 5 ].map( ( n ) => (
						<Button key={ n } isPressed={ rating === n } onClick={ () => setAttributes( { rating: n } ) }>
							{ n }
						</Button>
					) ) }
				</ButtonGroup>
			</div>
			<RichText
				tagName="p"
				className="lp-block-product-review__comment"
				value={ comment }
				onChange={ ( value ) => setAttributes( { comment: value } ) }
				placeholder="口コミコメントを入力"
			/>
		</div>
	);
}
