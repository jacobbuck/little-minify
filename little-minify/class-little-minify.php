<?php
/**
 * Little_Minify class
 * 
 * @package Little_Minify
 */

class Little_Minify {
	
	// Configuration Variables
	
	public $base_dir = '../';
	public $base_url = '/';
	public $css_embedding = true;
	public $css_embedding_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'ttf'  => 'font/truetype',
			'otf'  => 'font/opentype',
			'woff' => 'font/woff'
		);
	public $css_embedding_limit = 51200; // 50KB
	public $css_import = true;
	public $css_import_bubbling = 2;
	public $css_import_mediaqueries = true;
	public $concat_delimiter = ',';
	public $charset = 'utf-8';
	public $gzip = true;
	public $max_age = 86400;
	public $server_cache = 'file'; // apc, file or xcache
	
	
	// Misc Variables
	
	private $lib_dir;
	private $cache_dir;
	private $allowed_types = array(
			'css' => 'text/css',
			'js'  => 'application/javascript'
		);
	private $cache_prefix = 'lm-';
	private $use_base64;
	private $use_gzip;
	
	
	// Initialize
	
	public function __construct () {
				
		// Set directory variables
		$this->base_dir  = realpath( $this->base_dir );
		$this->lib_dir   = dirname( __FILE__ ) . '/lib';
		$this->cache_dir = dirname( __FILE__ ) . '/cache';
		
	}
	
	
	// Minify Files
	
	public function minify ( $files ) {
		
		// Get files real path, check if they exist, and get their last modified times
		$file_paths = array();
		$file_times = array();
		foreach ( (array) $files as $file ) {
			if ( $file_path = realpath( $this->base_dir . '/' . $file ) ) {
				array_push( $file_paths, $file_path );
				array_push( $file_times, filemtime( $file_path ) );
			}
		}
		
		// Return if no files
		if ( count( $file_paths ) < 1 )
			return false;
		
		// Get file type
		$file_type = substr( $files[0], strrpos( $files[0], '.' ) + 1 );
		
		// Return if file type not allowed
		if ( ! isset( $this->allowed_types[ $file_type ] ) )
			return false;
		
		// Get the latest last modified time
		$last_modified = max( $file_times );
		
		// Last modified header
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		
		// 304 not modified status header
		if ( @strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $last_modified ) {
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
		
		// Check if base64 available (ahem, not Internet Explorer 7 or less)
		$this->use_base64 = ( $this->css_embedding && ! preg_match( '/MSIE [1-7]/i', $_SERVER['HTTP_USER_AGENT'] ) );
		
		// Check if gzip available
		$this->use_gzip = ( $this->gzip && strstr( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) && extension_loaded('zlib') );
		
		// Generate cache file name
		$cache_name = $this->cache_prefix . md5( implode( ':)' , $file_paths ) . ( $this->use_base64 ? '-base64' : '' ) ) . '.' . $file_type . ( $this->use_gzip ? '.gz' : '' );
		
		// Expires headers
		if ( $this->max_age ) {
			header( 'Cache-Control: max-age=' . $this->max_age . ', must-revalidate' );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $this->max_age ) . ' GMT' );
		} else
			header( 'Cache-Control: must-revalidate' );
		
		// Content type header
		header( 'Content-Type: ' . $this->allowed_types[ $file_type ] . '; charset=' . $this->charset );
		
		// Gzip encoding header
		if ( $this->use_gzip )
			header('Content-Encoding: gzip');
		
		// Check if cache exists and up to date
		if ( $this->server_cache && $this->{ 'cache_' . $this->server_cache . '_last_modified' }( $cache_name ) > $last_modified ) {
			// Output minified from cache
			if ( $this->{ 'cache_' . $this->server_cache . '_output' }( $cache_name ) )
				exit;
		}
		
		// Continue if not cached yet
		
		// Get content
		$content = '';
		foreach ( (array) $file_paths as $file_path ) {
			// Get file contents
			$file_contents = file_get_contents( $file_path );
			if ( ! $file_contents ) // Skip if empty
				continue;
			// Process stylesheet contents 
			if ( 'css' === $file_type ) {
				$file_dirname  = dirname( $file_path );
				if ( $this->css_import )
					$file_contents = $this->css_bubble_import( $file_contents, $file_dirname );
				$file_contents = $this->css_convert_urls( $file_contents, $file_dirname );
			}
			// Append file contents
			$content .= $file_contents;
		}
		
		// Minify content
		$content = $this->{ $file_type . '_minify' }( $content );
		
		// Gzip content
		if ( $this->use_gzip )
			$content = gzencode( $content, 9 );
		
		// Write to cache
		if ( $this->server_cache )
			$this->{ 'cache_' . $this->server_cache . '_write' }( $cache_name, $content );
		
		// Output content
		echo $content;
		exit;
		
	}
	
	
	// CSS Minifier Functions
	
	public function css_minify ( $output ) {
		require_once( $this->lib_dir . '/cssmin.php' );
		$compressor = new CSSmin;
		return $compressor->run( $output );
	}
	
	public function css_bubble_import ( $output, $file_dirname, $depth = 1 ) {
		
		// Find and loop all @import url(reset.css); and @import 'reset.css' media-queries;
		$match_count = preg_match_all( '/@import\s*(url\()?[\'"]?([^\s\)\'";]*)[\'"]?\)?([^;]*);/i', $output, $matches );
		
		for ( $i = 0; $i < $match_count; $i++ ) {
			
			$new_file_path = realpath( $file_dirname . '/' . $matches[2][ $i ] );
			
			// Keep existing URL if file can't be found
			if ( ! $new_file_path )
				continue;
			
			// Get stylesheet contents and process
			$new_file_contents = file_get_contents( $new_file_path );
			$new_file_dirname  = dirname( $new_file_path );
			if ( $depth < $this->css_import_bubbling )
				$new_file_contents = $this->css_bubble_import( $new_file_contents, $new_file_dirname, $depth + 1 );
			$new_file_contents = $this->css_convert_urls( $new_file_contents, $new_file_dirname );
			
			// Wrap media query if avaliable
			if ( $this->css_import_mediaqueries && $matches[3][ $i ] )
				$new_file_contents = '@media '. $matches[3][ $i ] . ' {' . $new_file_contents . '}';
			
			// Replace @import with contents
			$output = str_replace( $matches[0][ $i ], $new_file_contents, $output );
			
		}
			
		return $output;
		
	}
		
	public function css_convert_urls ( $output, $file_dirname ) {
		
		// Split URL by ? or #
		$match_count = preg_match_all( '/url\([\'"]?([^\)\'"\?\#]*)([^\)\'"]*)[\'"]?\)/', $output, $matches );
				
		for ( $i = 0; $i < $match_count; $i++ ) {
						
			$new_file_path = realpath( $file_dirname . '/' . $matches[1][ $i ] );
			
			// Don't replace URL if file path can't be found
			if ( ! $new_file_path )
				continue;
			
			$new_file_type = substr( $new_file_path, strrpos( $new_file_path, '.' ) + 1 );
			
			// Check if base64 embededding allowed
			if (
				$this->use_base64 && // Embedding enabled and compatible
				! $matches[2][ $i ] && // URL doesn't contain ? or #
				isset( $this->css_embedding_types[ $new_file_type ] ) && // File type allowed
				substr_count( $output, $matches[1][ $i ] ) < 2 && // URL isn't already used more than once
				( ! $this->css_embedding_limit || filesize( $new_file_path ) < $this->css_embedding_limit ) // File under limit
			) {
				// Replace URL with Base64
				$output = str_replace( $matches[0][ $i ], 'url(data:' . $this->css_embedding_types[ $new_file_type ] . ';base64,' . base64_encode( file_get_contents( $new_file_path ) ) . ')', $output );
			} else {
				// Otherwise replace URL with absolute URL
				$output = str_replace( $matches[0][ $i ], 'url(' . str_replace( $this->base_dir . '/', $this->base_url, $new_file_path ) . $matches[2][ $i ] . ')', $output );
			}
			
		}
		
		return $output;
		
	}
	
	
	// JS Minifier Function
	
	public function js_minify ( $output ) {
		require_once( $this->lib_dir . '/jsmin.php' );
		return JSMin::minify( $output );
	}
	
	
	// Cache Functions
	
		// APC
	
	private function cache_apc_last_modified ( $name ) {
		return apc_fetch( $name . '-mtime' );
	}
	
	private function cache_apc_output ( $name ) {
		if ( $content = apc_fetch( $name ) ) {
			echo $content;
			return true;
		}
		return false;
	}
	
	private function cache_apc_write ( $name, $content ) {
		return apc_store( $name . '-mtime', time(), $this->max_age ) && apc_store( $name, $content, $this->max_age );
	}
	
		// File
	
	private function cache_file_last_modified ( $name ) {
		return @filemtime( $this->cache_dir . '/' . $name );
	}
	
	private function cache_file_output ( $name ) {
		return readfile( $this->cache_dir . '/' . $name );
	}
	
	private function cache_file_write ( $name, $content ) {
		if ( is_writable( $this->cache_dir ) )
			return file_put_contents( $this->cache_dir . '/' . $name, $content );
		return false;
	}
	
		// Xcache
	
	private function cache_xcache_last_modified ( $name ) {
		return xcache_get( $name . '-mtime' );
	}
	
	private function cache_xcache_output ( $name ) {
		if ( $content = xcache_get( $name ) ) {
			echo $content;
			return true;
		}
		return false;
	}
	
	private function cache_xcache_write ( $name, $content ) {
		return xcache_set( $name . '-mtime', time(), $this->max_age ) && xcache_set( $name, $content, $this->max_age );
	}
	
	
}