<?


mb_internal_encoding('UTF-8');


// --- PHP, RUN MODE ------------------------------------------------------ //


if (!defined('PHP_MAJOR_VERSION') || (PHP_MAJOR_VERSION + PHP_MINOR_VERSION / 10) < 5.4)
	die('PHP 5.4 or higher required');

$RUN_MODE = php_sapi_name() == 'cli' ? 'cli'
	: (preg_match('|^/+data/|', $_SERVER['REQUEST_URI']) ? 'data' : 'web');

// see content_type()
$CONTENT_TYPE = php_sapi_name() == 'cli' ? 'text/plain' : 'text/html';


// --- PATHS & CONFIG ----------------------------------------------------- //


$_ROOT = realpath(dirname(__FILE__) . '/../../');
$_HTDOCS = "$_ROOT/htdocs";
$_ETC = "$_ROOT/etc";
$_VAR = "$_ROOT/var";


function cfg_read_value($name, $default = '')
{
	// At the moment individual config values are in separate files in etc/
	global $_ETC;
	$fn = "$_ETC/$name";
	if (file_exists($fn))
		return trim(@file_get_contents($fn));
	else
		return $default;
}


$DEBUG_MODE = (int)cfg_read_value('DEBUG', 0);


// --- ERROR REPORTING ---------------------------------------------------- //


error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', $DEBUG_MODE ? 'On' : 'Off');


set_exception_handler(function($e) { fatal('(Uncaught exception) ' . $e->getMessage()); });


const DEBUG_CSS = 'text-align:left;background:#eed;padding:7px;border:3px solid #bba;font:small menlo;';
const FATAL_CSS = 'text-align:left;background:#eed;padding:7px;border:3px solid #bba;font:medium sans-serif;color:#b22;';


function fatal($msg)
{
	global $RUN_MODE, $CONTENT_TYPE;
	if ($RUN_MODE == 'cli')
	{
		fputs(STDERR, "Error: $msg\n");
		exit(255);
	}
	else if ($CONTENT_TYPE == 'text/plain')
		die("\nError: $msg\n");
	else if ($CONTENT_TYPE == 'text/xml')
		die('<fatal>' . htmlspecialchars($msg) . '</fatal>');
	else
		die('<p style="' . FATAL_CSS . '"><b>Error:</b> ' . htmlspecialchars($msg) . '</p>');
}


function internal($msg)
	{ fatal(' (Internal) ' . $msg); }


function notimpl($code = 0)
	{ internal('Feature not implemented yet' . ($code ? sprintf(' [%04x]', $code) : '')); }


function debug_dump($s)
{
	global $RUN_MODE, $CONTENT_TYPE;
	if ($RUN_MODE == 'cli')
		fputs(STDERR, "$s\n");
	else if ($CONTENT_TYPE == 'text/plain')
		echo "\n$s\n";
	else if ($CONTENT_TYPE == 'text/xml')
		echo "\n<debug>" . html($s) . "</debug>\n";
	else
		echo "\n<div style=\"", DEBUG_CSS, "\">", html($s), "</div>\n";
}


function _dump($v)
{
	global $CONTENT_TYPE;
	if ($CONTENT_TYPE != 'text/plain')
		echo '<pre style="', DEBUG_CSS, '">';
	var_dump($v);
	if ($CONTENT_TYPE != 'text/plain')
		echo '</pre>';
}


// --- SHORTCUTS ----------------------------------------------------------- //

if (get_magic_quotes_gpc())
	fatal($DEBUG_MODE ? 'PHP setting magic_quotes_gpc should be off' : 'Internal 1006');


function html($s)		{ return htmlspecialchars($s); }

function htmla($a)		{ return array_map(function ($x) { return html($x); }, $a); }

function jstr($s)		{ return '\'' . addcslashes($s, "/\\\'\n\r\t") . '\''; }

function sql_str($s)	{ return '\'' . addslashes($s) . '\''; }

function sql_str_array(array $a)
	{ return implode(',', array_map(function ($s) { return sql_str($s); }, $a)); }

function sql_like_str($s)
	{ return sprintf("'%s%%'", addcslashes($s, "\\\'%_")); }

function tesc($s)
	{ return addcslashes($s, "\\\t\n"); }

function strip_tags2($s)
	{ return strip_tags(preg_replace('/(<p|<li|<br|<div)/i', ' $1', $s)); }


function get_str($name, $default = '')
	{ return isset($_GET[$name]) ? $_GET[$name] : $default; }

function get_int($name, $default = 0)
	{ return isset($_GET[$name]) ? (int)$_GET[$name] : (int)$default; }

function post_str($name, $default = '')
	{ return isset($_POST[$name]) ? $_POST[$name] : $default; }

function post_int($name, $default = 0)
	{ return isset($_POST[$name]) ? (int)$_POST[$name] : (int)$default; }

function post_array($name)
	{ return isset($_POST[$name]) ?
		(is_array($_POST[$name]) ? $_POST[$name] : csv_to_array($_POST[$name])) : []; }


function form_submitted($form_name = 'form1')
	{ return isset($_REQUEST['_f']) && explode(';', $_REQUEST['_f'])[0] == $form_name; }

function form_submitted2($form_name = 'form1')
	{ return form_submitted($form_name) && isset($_REQUEST['_f_alt']) && (int)$_REQUEST['_f_alt']; }


function file_submitted($name = 'userfile')
{
	if (!isset($_FILES[$name]))
		return NULL;
	$f = $_FILES[$name];
	$o = new generic;
	$o->name = $f['name'];
	$o->tmp_name = $f['tmp_name'];
	$o->size = $f['size'];
	$o->type = $f['type'];
	$o->ext = strrchr($o->name, '.') ?: '';
	$o->error = $f['error'];
	return $o;
}


function files_submitted($name = 'userfiles')
{
	if (!isset($_FILES[$name]))
		return [];
	$f = $_FILES[$name];
	$a = [];
	foreach ($f['name'] as $i => $t)
	{
		$o = new generic;
		$o->name = $f['name'][$i];
		$o->tmp_name = $f['tmp_name'][$i];
		$o->size = $f['size'][$i];
		$o->type = $f['type'][$i];
		$o->ext = strrchr($o->name, '.') ?: '';
		$o->error = $f['error'][$i];
		$a[] = $o;
	}
	return $a;
}


function http_referer()
	{ return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''; }

function http_referer_uri()
{
	$url = http_referer();
	$prefix = http_proto() . '://' . http_host();
	return substr($url, 0, strlen($prefix)) == $prefix ? substr($url, strlen($prefix)) : '';
}

function return_uri($def_uri, $post_var = '_r')
	{ return post_str($post_var, http_referer_uri() ?: $def_uri); }

function http_request_uri()
	{ return $_SERVER['REQUEST_URI']; }

function http_request_path($i = 0)
{
	$a = explode('?', $_SERVER['REQUEST_URI'], 2);
	if (!$i)
		return $a[0];
	$a = explode('/', $a[0]);
	return isset($a[$i]) ? $a[$i] : '';
}

function http_proto()
	{ return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http'; }

function http_host()
	{ return $_SERVER['HTTP_HOST']; }

function http_request_url()
	{ return http_proto() . '://' . http_host() . http_request_uri(); ; }

function http_require_method($method)
	{ if ($_SERVER['REQUEST_METHOD'] != $method) fatal('Invalid request method'); }


// --- DATE/TIME ----------------------------------------------------------- //


function today()
	{ return @strftime('%Y-%m-%d'); }


function now()
	{ return @strftime('%Y-%m-%d %H:%M:%S'); }


function is_empty_date($date)
	{ return !$date || substr($date, 0, 5) == '0000-'; }


// --- MISC. --------------------------------------------------------------- //


function js_print_array($a)
{
	echo '[';
	$i = 0;
	foreach ($a as $v)
	{
		if ($i++ > 0) echo ',';
		echo is_int($v) ? $v : jstr((string)$v);
	}
	echo ']';
}


function js_print_assoc($a)
{
	echo '{';
	$i = 0;
	foreach ($a as $k => $v)
	{
		if ($i++ > 0) echo ',';
		echo is_int($k) && $k >= 0 ? $k : jstr((string)$k);
		echo ':';
		echo is_int($v) ? $v : jstr((string)$v);
	}
	echo '}';
}


function array_cast_to_int(&$a)
{
	if (!is_array($a) || count($a) == 0)
		return;
	array_walk($a, function (&$item) { $item = (int)$item; });
}


function csv_to_array($s)
	{ return $s != '' ? explode(',', $s) : []; }


function csv_to_int_array($s)
{
	$a = csv_to_array($s);
	array_cast_to_int($a);
	return $a;
}


function lines_to_array($lines)
	{ return $lines ?
		array_map(function ($i) { return stripslashes($i); }, explode("\n", $lines)) :
		[]; }

function array_to_lines($a)
	{ return $a ?
		implode("\n", array_map(function ($i) { return addcslashes($i, "\\\n"); }, $a)) :
		''; }


function array_to_int_csv($a)
	{ return implode(',', array_map(function ($i) { return (int)$i; }, $a)); }


function html_to_text($s)
	{ return html_entity_decode(str_replace('&nbsp;', ' ', strip_tags($s))); }


function str_prefix($s, $t)
	{ return substr($s, 0, strlen($t)) == $t ? substr($s, strlen($t)) : false; }


// --- MISC ---------------------------------------------------------------- //


function content_type($type)
{
	global $CONTENT_TYPE;
	header('Content-type: ' . ($CONTENT_TYPE = $type));
}


function output_file($file_path, $mime = NULL, $dont_cache = false, $filename = '')
{
	if (!$mime)
		$mime = @mime_content_type($file_path);
	if ($mime)
		content_type($mime);
	if ($dont_cache)
		header('Cache-control: must-revalidate');
	else
		header('Last-modified: ' . gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT'); 
	if ($filename)
		// header('Content-Disposition: attachment; filename=' . rawurlencode($filename));
		// TODO: filename for Safari
		header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
	@readfile($file_path);
}


function redirect($url)
{
	global $DEBUG_MODE;
	if ($DEBUG_MODE && ob_get_length())
		ob_flush(); // force printing of notices and diag messages, if any
	header("Location: $url");
	exit();
}


function begin_xml()
{
	content_type('text/xml');
	// Angle bracket + question mark is treated as embedded PHP, so:
	echo '<' . '?xml version="1.0" encoding="UTF-8" ?' . ">\n";
}


?>
