<?php
class Little_Minify {
	
	// Config
	public $base_dir = '../';
	public $base_url = '/';
	public $css_embedding = true;
	public $css_embedding_types = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'png'  => 'image/png',
			'ttf'  => 'font/truetype',
			'otf'  => 'font/opentype',
			'woff' => 'font/woff'
		);
	public $css_embedding_limit = 51200; // 50KB
	public $concat_delimiter = ',';
	public $charset = 'utf-8';
	public $gzip = true;
	public $max_age = 86400; // 24 hours
	public $server_cache = 'file'; // apc, file or xcache
	
	// Misc
	private $lib_dir;
	private $cache_dir;
	private $allowed_types = array(
			'css' => 'text/css',
			'js'  => 'application/javascript'
		);
	private $cache_prefix = 'lm-';
	private $use_gzip;
	
	public function __construct () {
		
		// Initialize
		
		// Set directory variables
		$this->base_dir  = realpath( $this->base_dir );
		$this->lib_dir   = dirname( __FILE__ ) . '/lib';
		$this->cache_dir = dirname( __FILE__ ) . '/cache';
		
		// Check if gzip available
		$this->use_gzip = ( $this->gzip && strstr( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) && extension_loaded('zlib') );
		
		// Start Minifying
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			
			// Get the query string and clean it up
			$query_string = urldecode( $_SERVER['QUERY_STRING'] );
			$query_string = substr( $query_string, 0, strpos( $query_string . '?', '?' ) );
			
			// Split the base dir and files from query string
			$last_slash = strrpos( $query_string, '/' );
			$dir = substr( $query_string, 0, $last_slash + 1 );
			$files = explode( $this->concat_delimiter, substr( $query_string, $last_slash + 1 ) );
			
			// Add dir to all files
			foreach ( $files as $key => $value )
				$files[ $key ] = $dir . $value;
			
			// Minify files
			$this->minify( $files );
			
			// 404 if error
			header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );
			exit;
			
		}
		
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
		
		// Generate cache file name
		$cache_name = $this->cache_prefix . md5( implode( ':)', $file_paths ) ) . '.' . $file_type . ( $this->use_gzip ? '.gz' : '' );
		
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
			// Convert URLs if CSS
			if ( 'css' === $file_type )
				$file_contents = $this->css_convert_urls( $file_contents, dirname( $file_path ) );
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
	
	private $css_convert_urls_tmp_dir;
	private $css_convert_urls_tmp_output;
	
	public function css_convert_urls ( $output, $dir ) {	
		$this->css_convert_urls_tmp_dir = $dir;
		$this->css_convert_urls_tmp_output = $output;
		return preg_replace_callback( '/url\([\'"]?([^\)\'"]+)[\'"]?\)/i', array( &$this, 'css_convert_urls_callback' ), $output );
	}
	
	private function css_convert_urls_callback ( $matches ) {
		
		// Split URL by ? or #
		preg_match( '/([^\?|\#]*)(.*)/', $matches[1], $matches );
		
		$file_path = realpath( $this->css_convert_urls_tmp_dir . '/' . $matches[1] );
		
		// Return existing URL if file can't be found
		if ( ! $file_path )
			return 'url(' . $matches[0] . ')';
		
		$file_type = substr( $file_path, strrpos( $file_path, '.' ) + 1 );
		
		// Return base64 embeded if allowed (based on file size and type)
		if (
			$this->css_embedding &&
			isset( $this->css_embedding_types[ $file_type ] ) &&
			substr_count( $this->css_convert_urls_tmp_output, $matches[1] ) < 2 &&
			( ! $this->css_embedding_limit || filesize( $file_path ) < $this->css_embedding_limit )
		)
			return 'url(data:' . $this->css_embedding_types[ $file_type ] . ';base64,' . base64_encode( file_get_contents( $file_path ) ) . ')';
		
		// Return absolute URL
		return 'url(' . str_replace( $this->base_dir . '/', $this->base_url, $file_path ) . $matches[2] . ')';
		
	}
	
	
	// JS Minifier Function
	
	public function js_minify ( $output ) {
		require_once( $this->lib_dir . '/jsminplus.php' );
		return JSMinPlus::minify( $output );
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