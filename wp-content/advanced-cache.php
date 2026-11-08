<?php
/**
 * SG Page Cache — early-serve drop-in (wp-content/advanced-cache.php).
 *
 * WordPress includes this file in wp-settings.php when WP_CACHE is on, BEFORE the database,
 * plugins and mu-plugins load. It serves the exact cache files written by the SG Page Cache
 * mu-plugin (same on-disk format: 3-byte status prefix + HTML), so a warm page costs a file
 * read instead of a full WordPress bootstrap. It NEVER stores: storing, purging and every
 * other rule stay with the mu-plugin, which also remains the fallback server whenever this
 * drop-in declines (stale file, bypassed request, missing constant).
 *
 * The bypass rules and the cache-key formula below intentionally mirror kzpc_bypass() and
 * kzpc_file() in mu-plugins/kz-page-cache.php — including core's wp_is_mobile() test, which
 * the mu-plugin uses for the device variant. Keep the two in sync when either changes.
 */

if ( ! defined( 'ABSPATH' ) ) { return; }

/* Only ever serve a plain front-end GET; everything else falls through to WordPress. */
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { return; }
if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) { return; }
if ( ! isset( $_SERVER['REQUEST_URI'] ) ) { return; }

$kzac_uri = (string) $_SERVER['REQUEST_URI'];
if ( false !== strpos( $kzac_uri, '/wp-' ) || false !== strpos( $kzac_uri, 'preview' ) || false !== strpos( $kzac_uri, 'feed' ) ) { return; }
if ( isset( $_SERVER['QUERY_STRING'] ) && '' !== (string) $_SERVER['QUERY_STRING'] ) { return; }
foreach ( array_keys( $_COOKIE ) as $kzac_c ) {
	if ( preg_match( '/^(wordpress_logged_in|wp-postpass|comment_author)/', (string) $kzac_c ) ) { return; }
}
if ( isset( $_COOKIE['wordpress_test_cookie'] ) ) { return; }

/* Device variant — core wp_is_mobile()'s test, verbatim (the function itself is not loaded yet). */
$kzac_ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
$kzac_mobile   = '' !== $kzac_ua && ( false !== strpos( $kzac_ua, 'Mobile' ) || false !== strpos( $kzac_ua, 'Android' )
	|| false !== strpos( $kzac_ua, 'Silk/' ) || false !== strpos( $kzac_ua, 'Kindle' )
	|| false !== strpos( $kzac_ua, 'BlackBerry' ) || false !== strpos( $kzac_ua, 'Opera Mini' )
	|| false !== strpos( $kzac_ua, 'Opera Mobi' ) );
$kzac_host     = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
$kzac_dir      = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
$kzac_file     = $kzac_dir . '/cache/kz-page/' . md5( $kzac_host . $kzac_uri . ( $kzac_mobile ? '|m' : '|d' ) ) . '.html';
$kzac_ttl      = 1800; /* KZPC_TTL in the mu-plugin: 30 minutes. */

if ( is_readable( $kzac_file ) && ( time() - filemtime( $kzac_file ) ) < $kzac_ttl ) {
	$kzac_head = @file_get_contents( $kzac_file, false, null, 0, 3 );
	if ( '200' === $kzac_head ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Vary: User-Agent' );   /* cache is keyed per device class; proxies must not mix them */
		header( 'X-KZ-Cache: hit' );
		$kzac_fp = fopen( $kzac_file, 'rb' );
		if ( $kzac_fp ) {
			fseek( $kzac_fp, 3 );       /* skip the 3-byte status prefix */
			fpassthru( $kzac_fp );
			fclose( $kzac_fp );
		}
		exit;
	}
}
