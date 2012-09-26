# Little Minify #

An awesome little CSS and JS minifier written in PHP.

## Features ##

- Combines and minify stylesheet and javascript files
- Server-side caching (file-based) minified files
- Client-size caching headers
- Gzip encoding
- Base64 embedding images in stylesheets
- Uses [Tubal Martin's PHP port of the YUI CSS compressor](https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port) and [JSMinPlus](http://crisp.tweakblogs.net/blog/cat/716).

## To Do ##

- Easier configuration and better debugging options
- CSS @import bubbling
- Detect duplicate image embeds
- HTTP 304 (Not Modified) responses
- More server-side caching options (Memcache, APC, Xcache, etc)