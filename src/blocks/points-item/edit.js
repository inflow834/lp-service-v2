import { useBlockProps, RichText, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { heading, body, imageId, imageUrl } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-points-item' } );

	return (
		<div { ...blockProps }>
			<div className="lp-block-points-item__image">
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ) }
						allowedTypes={ [ 'image' ] }
						value={ imageId }
						render={ ( { open } ) => (
							<Button onClick={ open } variant="secondary">
								{ imageUrl ? <img src={ imageUrl } alt="" /> : '画像を選択（任意）' }
							</Button>
						) }
					/>
				</MediaUploadCheck>
				{ imageUrl && (
					<Button
						variant="link"
						isDestructive
						onClick={ () => setAttributes( { imageId: 0, imageUrl: '' } ) }
					>
						画像を削除
					</Button>
				) }
			</div>
			<div className="lp-block-points-item__text">
				<RichText tagName="h3" className="lp-block-points-item__heading" value={ heading } onChange={ ( v ) => setAttributes( { heading: v } ) } placeholder="ポイントの見出し" allowedFormats={ [] } />
				<RichText tagName="p" className="lp-block-points-item__body" value={ body } onChange={ ( v ) => setAttributes( { body: v } ) } placeholder="本文を入力" />
			</div>
		</div>
	);
}
