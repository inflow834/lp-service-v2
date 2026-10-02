import { useBlockProps, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { imageId, imageUrl } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-simple-banner' } );

	return (
		<div { ...blockProps }>
			<MediaUploadCheck>
				<MediaUpload
					onSelect={ ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ) }
					allowedTypes={ [ 'image' ] }
					value={ imageId }
					render={ ( { open } ) => (
						<Button onClick={ open } variant="secondary" className="lp-block-simple-banner__button">
							{ imageUrl ? <img src={ imageUrl } alt="" /> : 'バナー画像を選択' }
						</Button>
					) }
				/>
			</MediaUploadCheck>
			{ imageUrl && (
				<Button variant="link" isDestructive onClick={ () => setAttributes( { imageId: 0, imageUrl: '' } ) }>
					画像を削除
				</Button>
			) }
		</div>
	);
}
