<?php
/**
 * GitHub Releases からのテーマ自動アップデート
 *
 * WordPress標準の「Update URI」ヘッダー機構（WP 6.1+）を利用する。
 * style.css の `Update URI: https://github.com/{owner}/{repo}` からリポジトリを
 * 割り出すため、リポジトリ名をコードに固定しない（移管・改名に強い）。
 * 公開リポジトリ前提のためトークンは一切使わない。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LP_SERVICE_UPDATER_SLUG  = 'lp-service-v2';
const LP_SERVICE_UPDATER_ASSET = 'lp-service-v2.zip';
const LP_SERVICE_UPDATER_CACHE = 'lp_service_updater_release';

/**
 * Update URI からリポジトリ（owner/repo）を取り出す。
 * github.com 以外・形式不正・プレースホルダー(OWNER)のままの場合は null。
 */
function lp_service_updater_parse_repo( $update_uri ) {
	$parts = wp_parse_url( (string) $update_uri );
	if ( empty( $parts['host'] ) || 'github.com' !== strtolower( $parts['host'] ) || empty( $parts['path'] ) ) {
		return null;
	}
	if ( ! preg_match( '#^/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $parts['path'], $m ) ) {
		return null;
	}
	// 配布前の仮置き値のままなら無駄なAPI通信をしない
	if ( 'OWNER' === $m[1] ) {
		return null;
	}
	return array( $m[1], $m[2] );
}

/**
 * ダウンロードURLが想定どおりのGitHub配布元か検証する（改ざん・誘導対策）。
 */
function lp_service_updater_is_safe_package( $url, $owner, $repo ) {
	$parts = wp_parse_url( (string) $url );
	if ( empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return false;
	}
	$host = strtolower( $parts['host'] );
	if ( 'github.com' === $host ) {
		return 0 === strpos( $parts['path'], "/{$owner}/{$repo}/releases/download/" );
	}
	return in_array( $host, array( 'objects.githubusercontent.com', 'release-assets.githubusercontent.com' ), true );
}

/**
 * 最新リリース情報を取得する。失敗時も短時間キャッシュし、
 * 認証なしAPIのレート制限（60回/時/IP）に当たらないようにする。
 *
 * @return array|null array( version, url, package ) / 取得不可なら null
 */
function lp_service_updater_get_release( $owner, $repo ) {
	$cached = get_site_transient( LP_SERVICE_UPDATER_CACHE );
	if ( is_array( $cached ) && ( $cached['repo'] ?? '' ) === "{$owner}/{$repo}" ) {
		return $cached['release'];
	}

	$release  = null;
	$response = wp_remote_get(
		'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'lp-service-theme-updater',
			),
		)
	);

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $data ) && ! empty( $data['tag_name'] ) && is_array( $data['assets'] ?? null ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === LP_SERVICE_UPDATER_ASSET
					&& lp_service_updater_is_safe_package( $asset['browser_download_url'] ?? '', $owner, $repo ) ) {
					$release = array(
						'version' => ltrim( (string) $data['tag_name'], 'vV' ),
						'url'     => esc_url_raw( $data['html_url'] ?? '' ),
						'package' => $asset['browser_download_url'],
					);
					break;
				}
			}
		}
	}

	set_site_transient( LP_SERVICE_UPDATER_CACHE, array( 'repo' => "{$owner}/{$repo}", 'release' => $release ), 10 * MINUTE_IN_SECONDS );
	return $release;
}

/**
 * `update_themes_github.com` フィルター: 新バージョンがあれば更新情報を返す。
 * 返り値が配列で新しければ WP が $transient->response に載せ、
 * 管理画面の「更新」ボタンから標準の仕組みでアップデートできる。
 */
function lp_service_updater_check( $update, $theme_data, $theme_stylesheet ) {
	if ( LP_SERVICE_UPDATER_SLUG !== $theme_stylesheet ) {
		return $update;
	}
	$repo = lp_service_updater_parse_repo( $theme_data['UpdateURI'] ?? '' );
	if ( ! $repo ) {
		return $update;
	}

	$release = lp_service_updater_get_release( $repo[0], $repo[1] );
	if ( ! $release || '' === $release['version'] || ! version_compare( $release['version'], $theme_data['Version'] ?? '0', '>' ) ) {
		return $update;
	}

	return array(
		'id'           => $theme_data['UpdateURI'],
		'theme'        => $theme_stylesheet,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $theme_data['RequiresWP'] ?? '',
		'requires_php' => $theme_data['RequiresPHP'] ?? '',
	);
}
add_filter( 'update_themes_github.com', 'lp_service_updater_check', 10, 3 );

/**
 * 「再確認」(update-core.php?force-check=1) やテーマ更新後は、自前キャッシュも破棄する。
 * 残すと最大10分間、古い判定のままになるため。
 */
function lp_service_updater_clear_cache() {
	delete_site_transient( LP_SERVICE_UPDATER_CACHE );
}
add_action( 'delete_site_transient_update_themes', 'lp_service_updater_clear_cache' );

function lp_service_updater_after_upgrade( $upgrader, $hook_extra ) {
	if ( ( $hook_extra['type'] ?? '' ) === 'theme' && in_array( LP_SERVICE_UPDATER_SLUG, (array) ( $hook_extra['themes'] ?? array() ), true ) ) {
		lp_service_updater_clear_cache();
	}
}
add_action( 'upgrader_process_complete', 'lp_service_updater_after_upgrade', 10, 2 );
