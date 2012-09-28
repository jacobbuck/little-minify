<?php
require_once('class-little-minify.php');
require_once('config.php');

// Start Minifying
if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
	
	$little_minify = new Little_Minify( $config );
	
	// Get the query string and clean it up
	$query_string = urldecode( $_SERVER['QUERY_STRING'] );
	$query_string = substr( $query_string, 0, strpos( $query_string . '?', '?' ) );
	
	// Split the base dir and files from query string
	$last_slash = strrpos( $query_string, '/' );
	$dir = substr( $query_string, 0, $last_slash + 1 );
	$files = explode( $little_minify->concat_delimiter, substr( $query_string, $last_slash + 1 ) );
	
	// Add dir to all files
	foreach ( $files as $key => $value )
		$files[ $key ] = $dir . $value;
	
	// Minify files
	$little_minify->minify( $files );
	
}

// 404 if error
header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
exit;