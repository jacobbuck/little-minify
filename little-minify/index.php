<?php
/**
 * Little Minify front end loader
 * 
 * @package Little_Minify
 */

require('class-little-minify.php');
require('config.php');

// Start Minifying
if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
	
	$little_minify = new Little_Minify();
	
	// Configuration overrides
	foreach ( (array) $config as $key => $val )
		if ( isset( $little_minify->{ $key } ) )
			$little_minify->{ $key } = $val;
	
	// Get the query string and clean it up
	$query_string = urldecode( $_SERVER['QUERY_STRING'] );
	$query_string = substr( $query_string, 0, strpos( $query_string . '?', '?' ) );
	
	// Split files from query string
	$files = explode( ',', $query_string );
	$files_count = count( $files );
	
	// Split dir from first file and add to remaining files
	if ( $files_count > 1 ) {
		$dir = substr( $files[0], 0, strrpos( $files[0], '/' ) + 1 );
		for ( $i = 1; $i < $files_count; $i++ ) 
			$files[ $i ] = $dir . $files[ $i ];		
	}
	
	// Minify files
	$little_minify->minify( $files );
	
}

// 404 if error
header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
exit;