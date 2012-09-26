<?php
class Little_Minify {
	
	// Config
	private $base_dir = '../';
	private $base_url = '/projects/little-minify/';
	private $css_embedding = true;
	private $css_embedding_types = array( 
			'jpg'  => 'image/jpeg', 
			'jpeg' => 'image/jpeg', 
			'gif'  => 'image/gif', 
			'png'  => 'image/png'
		);
	private $css_embedding_limit = 10240; // 10KB
	private $concat_delimiter = ',';
	private $charset = 'utf-8';
	
	// Directories
	private $lib_dir;
	private $cache_dir;
	
	// Misc
	private $allowed_types = array( 
			'css' => 'text/css', 
			'js'  => 'application/javascript'
		);
	private $cache_prefix = 'lm-';
	private $current_dir;
	private $use_gzip;
	
	public function __construct () {		
		
		// Initialize
		$this->init();
		
		// Get the query string and clean it up
		$query_string = urldecode( $_SERVER['QUERY_STRING'] );
		$query_string = reset( explode( '?', $query_string ) ); // remove ?=junk
		
		// Split the base dir and files from query string
		$this->current_dir = substr( $query_string, 0, strrpos( $query_string, '/' ) + 1 );
		$files = explode( $this->concat_delimiter, substr( $query_string, strrpos( $query_string, '/' ) + 1 ) );
		
		// 404 if no files
		if ( ! $files )
			return $this->output_404();
		
		// Get file type
		$file_type = substr( $files[0], strrpos( $files[0], '.' ) + 1 );
		
		// Check if file type allowed
		if ( ! isset( $this->allowed_types[ $file_type ] ) )
			return $this->output_404();
		
		// Get an array of files to minify, and their last modified times
		$file_paths = array();
		$file_times = array();
		foreach ( (array) $files as $file ) {
			
			$file_path = realpath( $this->base_dir . '/' . $this->current_dir . $file );
			
			if ( ! $file_path )
				continue;
			
			array_push( $file_paths, $file_path );
			array_push( $file_times, filemtime( $file_path ) );
			
		}
		
		// 404 if no real files
		if ( ! count( $file_paths ) )
			return $this->output_404();
		
		// Generate cache file name
		$cache_name = $this->cache_prefix . md5( $query_string ) . '.' . $file_type . ( $this->use_gzip ? '.gz' : '' );
		$cache_path = $this->cache_dir . '/' . $cache_name;
				
		// Expire in 24 hours
		header('Cache-Control: max-age=86400, must-revalidate');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
		
		// Content Type
		header('Content-Type: ' . $this->allowed_types[ $file_type ] . '; charset=' . $this->charset);
		
		// Gzipped
		if ( $this->use_gzip ) {
			header('Content-Encoding: gzip');
		}
		
		// Check if cache exists and up to date
		if ( file_exists( $cache_path ) && filemtime( $cache_path ) >= max( $file_times ) ) {
			
			// File size and last modified
			header('Content-Length: ' . filesize( $cache_path ));
			header('Last-Modified: ' . filemtime( $cache_path ));
			
			// Get minified from cache
			readfile( $cache_path );
			
		} else {
			
			// Minify files and cache
			$content = '';
			foreach ( (array) $file_paths as $file_path ) {
				$content .= file_get_contents( $file_path );
			}
			$content = $this->{ $file_type . '_minify' }( $content );
			if ( $this->use_gzip ) {
				$content = gzencode( $content, 9 );
			}
			if ( is_writable( $this->cache_dir ) ) {
				file_put_contents( $cache_path, $content );
			}
		
			// File size and last modified
			header('Content-Length: ' . strlen( $content ));
			header('Last-Modified: ' . max( $file_times ));
			
			// Output Minified
			echo $content;
			
		}
		
		// We're done here
		exit;
		
	}	
	
	private function init () {
		
		// Set directory variables
		$this->base_dir  = realpath( $this->base_dir );
		$this->lib_dir   = dirname( __FILE__ ) . '/lib';
		$this->cache_dir = dirname( __FILE__ ) . '/cache';
		
		// Check if gzip available
		$this->use_gzip = ( in_array( 'gzip', explode( ',', $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) && function_exists('gzencode') );
		
	}
	
	
	/* CSS Minifier Functions */
	
	private function css_minify ( $output ) {
		require_once( $this->lib_dir . '/cssmin.php' );
		$compressor = new CSSmin();
		return $compressor->run( $this->css_convert_urls( $output ) );
	}
	
	private $css_convert_urls_tmp_path = '';
	
	private function css_convert_urls ( $output ) {	
		return preg_replace_callback( '/url\s*\([\'"]?([^\)\'"]+)[\'"]?\)/', array( &$this, 'css_convert_urls_callback' ), $output );
	}
	
	private function css_convert_urls_callback ( $matches ) {
		
		$file_path = realpath( $this->base_dir . '/' . $this->current_dir . '/' . reset( explode( '?', $matches[1] ) ) );
		
		// Return existing URL if file can't be found
		if ( ! $file_path )
			return 'url(' . $matches[1] . ')';
		
		$file_type = substr( $file_path, strrpos( $file_path, '.' ) + 1 );
		
		// Return base64 embeded if extension supported
		if ( $this->css_embedding && isset( $this->css_embedding_types[ $file_type ] ) && filesize( $file_path ) < $this->css_embedding_limit )
			return 'url(data:' . $this->css_embedding_types[ $file_type ] . ';base64,' . base64_encode( file_get_contents( $file_path ) ) . ')';
		
		// Return full absolute url
		return 'url(' . str_replace( $this->base_dir . '/', $this->base_url, $file_path ) . ')';
		
	}
	
	/* JS Minifier Functions */
	
	private function js_minify ( $output ) {
		require_once( $this->lib_dir . '/jsminplus.php' );
		$minified = JSMinPlus::minify( $output );
		return $minified;
	}
	
	 
	/* Output 404 */
	
	private function output_404 () {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' ); 
		exit;
	}
	
}