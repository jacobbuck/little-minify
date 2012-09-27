# Little Minify #

An awesome little CSS and JS minifier written in PHP.

## Features ##

- Combines and minifies css and javascript files
- Server-side caching minified files (APC, File or Xcache)
- Client-size caching (HTTP 304 Not Modified, Expires and Cache-Control headers)
- Gzip compression
- Base64 embedding images and fonts in stylesheets
- Uses [Tubal Martin's PHP port of the YUI CSS compressor](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port) and [JSMinPlus](http://crisp.tweakblogs.net/blog/cat/716).

## To Do ##

- Easier configuration and better debugging options
- CSS @import bubbling
- Detect embedding duplicate images (ie. css sprites)

## Requirements ##

- PHP 5.2.1+ (with Zlib for Gzip compression)