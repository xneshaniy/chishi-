<?php
/** Dev router for the PHP built-in server ONLY (emulates the .htaccess /admin and /management rewrites).
 *  Production uses .htaccess on Apache/LiteSpeed and never touches this file. */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

// Serve any real file directly (route rewrites only for non-existing paths)
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && is_file($file)) return false;

// Static file extension shortcuts
if (preg_match('/\.(css|js|svg|png|jpg|jpeg|gif|webp|ico|woff2?)$/i', $uri)) return false;

// Area rewrites (mirror .htaccess)
if ($uri === '/admin' || $uri === '/admin/') { $_GET['r'] = 'admin'; }
elseif (str_starts_with($uri, '/admin/')) { $_GET['r'] = 'admin/' . trim(substr($uri, 7), '/'); }
if ($uri === '/management' || $uri === '/management/') { $_GET['r'] = 'management'; }
elseif (str_starts_with($uri, '/management/')) { $_GET['r'] = 'management/' . trim(substr($uri, 12), '/'); }

require __DIR__ . '/index.php';
