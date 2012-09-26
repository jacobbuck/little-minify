<?php
class Little_Minify {
	
	// Config
	private $base_dir = '../';
	private $base_url = '/';
	private $css_embedding = true;
	private $css_embedding_types = array( 
			'jpg'  => 'image/jpeg', 
			'jpeg' => 'image/jpeg', 
			'gif'  => 'image/gif', 
			'png'  => 'image/png',
			'ttf'  => 'font/truetype',
			'otf'  => 'font/opentype',
			'woff' => 'font/woff'
		);
	private $css_embedding_limit = 51200; // 50KB
	private $concat_delimiter = ',';
	private $charset = 'utf-8';
	
	// Misc
	private $lib_dir;
	private $cache_dir;
	private $allowed_types = array( 
			'css' => 'text/css', 
			'js'  => 'application/javascript'
		);
	private $cache_prefix = 'lm-';
	private $current_dir;
	private $use_gzip;
	
	public function __construct () {		
		
		// Initialize
		
		// Set directory variables
		$this->base_dir  = realpath( $this->base_dir );
		$this->lib_dir   = dirname( __FILE__ ) . '/lib';
		$this->cache_dir = dirname( __FILE__ ) . '/cache';
		
		// Check if gzip available
		$this->use_gzip = ( in_array( 'gzip', explode( ',', $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) && extension_loaded('zlib') );
		
		// Start Minifying
		
		// Get the query string and clean it up
		$query_string = urldecode( $_SERVER['QUERY_STRING'] );
		$query_string = reset( explode( '?', $query_string ) ); // remove any ?=junk
		
		// Split the base dir and files from query string
		$this->current_dir = substr( $query_string, 0, strrpos( $query_string, '/' ) + 1 );
		$files = explode( $this->concat_delimiter, substr( $query_string, strrpos( $query_string, '/' ) + 1 ) );
		
		// 404 if no files
		if ( ! $files )
			$this->exit_404();
		
		// Get file type
		$file_type = substr( $files[0], strrpos( $files[0], '.' ) + 1 );
		
		// 404 if file type not allowed
		if ( ! isset( $this->allowed_types[ $file_type ] ) )
			$this->exit_404();
		
		// Get an array of files to minify, and their last modified times
		$file_paths = array();
		$file_times = array();
		foreach ( (array) $files as $file ) {
			
			$file_path = realpath( $this->base_dir . '/' . $this->current_dir . $file );
			
			if ( ! $file_path ) // Skip if file can't be found
				continue;
			
			array_push( $file_paths, $file_path );
			array_push( $file_times, filemtime( $file_path ) );
			
		}
		
		// 404 if no real files
		if ( ! count( $file_paths ) )
			$this->exit_404();
		
		// Get the largest last modified time
		$last_modified = max( $file_times );
		
		// Last modified header
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		
		// 304 not modified status header
		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $last_modified ) { 
		    header('HTTP/1.1 304 Not Modified'); 
		    exit; 
		}		
				
		// Generate cache file name
		$cache_name = $this->cache_prefix . md5( implode( ':)', $file_paths ) );
		$cache_file = $this->cache_dir . '/' . $cache_name . '.' . $file_type . ( $this->use_gzip ? '.gz' : '' );
				
		// Expire in 24 hours ( 60 * 60 * 24 )
		header( 'Cache-Control: max-age=86400, must-revalidate' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 86400 ) . ' GMT' );
		
		// Content type header
		header( 'Content-Type: ' . $this->allowed_types[ $file_type ] . '; charset=' . $this->charset );
		
		// Gzip encoding header
		if ( $this->use_gzip ) 
			header('Content-Encoding: gzip');
		
		// Check if cache exists and up to date
		if ( file_exists( $cache_file ) && filemtime( $cache_file ) > $last_modified ) {
			// Output minified from cache
			readfile( $cache_file );
			exit;
		}
		
		// Continue if not cached yet
				
		// Output and buffer files
		ob_start();
		foreach ( (array) $file_paths as $file_path ) 
			readfile( $file_path );
		$content = ob_get_clean();
		
		// Minify content
		$content = $this->{ $file_type . '_minify' }( $content );
		
		// Gzip content
		if ( $this->use_gzip ) 
			$content = gzencode( $content, 9 );
		
		// Write to cache
		if ( is_writable( $this->cache_dir ) ) 
			file_put_contents( $cache_file, $content );
		
		// Output content
		echo $content;
		exit;
		
	}
	
	
	// CSS Minifier Functions
	
	private function css_minify ( $output ) {
		require_once( $this->lib_dir . '/cssmin.php' );
		$compressor = new CSSmin;
		return $compressor->run( $this->css_convert_urls( $output ) );
	}
		
	private function css_convert_urls ( $output ) {	
		return preg_replace_callback( '/url\([\'"]?([^\)\'"]+)[\'"]?\)/i', array( &$this, 'css_convert_urls_callback' ), $output );
	}
	
	private function css_convert_urls_callback ( $matches ) {
		
		$file_path = realpath( $this->base_dir . '/' . $this->current_dir . '/' . reset( explode( '?', $matches[1] ) ) );
		
		// Return existing URL if file can't be found
		if ( ! $file_path )
			return 'url(' . $matches[1] . ')';
		
		$file_type = substr( $file_path, strrpos( $file_path, '.' ) + 1 );
		
		// Return base64 embeded if allowed (based on file size and type)
		if ( $this->css_embedding && isset( $this->css_embedding_types[ $file_type ] ) && ( ! $this->css_embedding_limit || filesize( $file_path ) < $this->css_embedding_limit ) )
			return 'url(data:' . $this->css_embedding_types[ $file_type ] . ';base64,' . base64_encode( file_get_contents( $file_path ) ) . ')';
		
		// Return absolute URL
		return 'url(' . str_replace( $this->base_dir . '/', $this->base_url, $file_path ) . ')';
		
	}
	
	
	// JS Minifier Function
	
	private function js_minify ( $output ) {
		require_once( $this->lib_dir . '/jsminplus.php' );
		return JSMinPlus::minify( $output );
	}
	
	 
	// Exit with 404 status header
	
	private function exit_404 () {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' ); 
		exit;
	}
	
	
}