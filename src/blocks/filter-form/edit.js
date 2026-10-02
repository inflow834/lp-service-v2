import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { TextControl, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { heading, buttonText } = attributes;
	const blockProps = useBlockProps( { className: 'lp-block-filter-form' } );
	const postId = useSelect( ( select ) => select( editorStore ).getCurrentPostId(), [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title="絞り込みフォーム設定">
					<TextControl label="見出し" value={ heading } onChange={ ( value ) => setAttributes( { heading: value } ) } />
					<TextControl label="ボタンのテキスト" value={ buttonText } onChange={ ( value ) => setAttributes( { buttonText: value } ) } />
					<p className="lp-block-filter-form__hint">セレクト・チェックボックスの項目自体は、このLPの編集画面上部にある「絞り込みフォーム設定」で管理してください。</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender block="lp-service/filter-form" attributes={ attributes } urlQueryArgs={ { post_id: postId } } />
			</div>
		</>
	);
}
