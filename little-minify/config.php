<?php
/**
 * Configuration file for Little Minify front end loader
 * 
 * @package Little_Minify
 */

$config = array();

/**
 * Little Minify Directory
 * eg. '/var/www/vhosts/example.com/httpdocs/little-minify/' or '../'
 */
// $config['base_dir'] = '../';

/**
 * Base Site URL
 * eg. 'http://example.com/' or '/'
 */
// $config['base_url'] = '/';

/**
 * Enable base64 embedding in stylesheets
 */
// $config['css_embedding'] = true;

/**
 * File types to base64 embed
 * eg. 'ext' => 'mime/type'
 */
// $config['css_embedding_types'] = array(
// 	'jpg'  => 'image/jpeg',
// 	'jpeg' => 'image/jpeg',
// 	'gif'  => 'image/gif',
// 	'png'  => 'image/png',
// 	'webp' => 'image/webp',
// 	'ttf'  => 'font/truetype',
// 	'otf'  => 'font/opentype',
// 	'woff' => 'font/woff'
// );

/**
 * File limit of base64 embedding (in bytes)
 */
// $config['css_embedding_limit'] = 51200;

/**
 * Enable CSS @import bubbling
 */
// $config['css_import'] = true;

/**
 * CSS @import bubbling depth limit
 */
// $config['css_import_bubbling'] = 2;

/**
 * Wrap @media around imported CSS @import's with media queries
 */
// $config['css_import_mediaqueries'] = true;

/**
 * Delimiter (seperator) of concatination in URLs
 */
// $config['concat_delimiter'] = ',';

/**
 * Character set
 */
// $config['charset'] = 'utf-8';

/**
 * Enable gzip compression
 */
//$config['gzip'] = true;

/**
 * Maximum age of cache (in seconds)
 * This is both client-side, and APC/Xcache server-side
 */
// $config['cache_max_age'] = 86400;

/**
 * Enable and set server-side caching
 * Can be 'file', 'apc', 'xcache' or false
 */
// $config['server_cache'] = 'file';
