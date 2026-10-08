import { PanelBody, ToggleControl } from '@wordpress/components';

/**
 * ボタンのサイドバーに出す「新しいタブで開く」設定（既定ON）
 * target="_blank" は保存HTMLではなく表示時に付ける（inc/cushion.php の lp_service_cushion_render_block()）
 */
export default function NewTabPanel( { attributes, setAttributes } ) {
	const { openInNewTab = true } = attributes;

	return (
		<PanelBody title="リンク">
			<ToggleControl
				label="新しいタブで開く"
				checked={ openInNewTab }
				onChange={ ( value ) => setAttributes( { openInNewTab: value } ) }
			/>
		</PanelBody>
	);
}
