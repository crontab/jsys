<?


mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Yerevan');

umask(0002);


// --- PHP, RUN MODE ------------------------------------------------------ //


if (!defined('PHP_MAJOR_VERSION') || (PHP_MAJOR_VERSION + PHP_MINOR_VERSION / 10) < 5.4)
	die('PHP 5.4 or higher required');

$RUN_MODE = php_sapi_name() == 'cli' ? 'cli'
	: (preg_match('|^/+data/|', $_SERVER['REQUEST_URI']) ? 'data' : 'web');


function content_type($type = NULL)
{
	global $RUN_MODE;
	static $CONTENT_TYPE;
	if ($type)
		header('Content-type: ' . ($CONTENT_TYPE = $type));
	else if (!$CONTENT_TYPE)
		$CONTENT_TYPE = $RUN_MODE == 'web' ? 'text/html' : 'text/plain';
	return $CONTENT_TYPE;
}


// --- PATHS & CONFIG ----------------------------------------------------- //


$_ROOT = realpath(dirname(__FILE__) . '/../../');
$_HTDOCS = "$_ROOT/htdocs";
$_ETC = "$_ROOT/etc";
$_VAR = "$_ROOT/var";
$_SCRIPTS = "$_ROOT/scripts";


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

function is_debug_mode()
	{ global $DEBUG_MODE; return $DEBUG_MODE; }


// --- ERROR REPORTING ---------------------------------------------------- //


if ($DEBUG_MODE)
{
	error_reporting(E_ALL | E_STRICT);
	ini_set('display_errors', 'On');
}


set_exception_handler(function($e) { fatal('(Uncaught exception) ' . $e->getMessage()); });


const DEBUG_CSS = 'text-align:left;background:#eed;padding:7px;border:3px solid #bba;font:small menlo;';
const FATAL_CSS = 'text-align:left;background:#eed;padding:7px;border:3px solid #bba;font:medium sans-serif;color:#b22;';


function fatal($msg)
{
	global $RUN_MODE;
	if ($RUN_MODE == 'cli')
	{
		fputs(STDERR, "Error: $msg\n");
		exit(255);
	}
	else if (content_type() == 'text/plain')
		die("\nError: $msg\n");
	else if (content_type() == 'text/xml')
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
	global $RUN_MODE;
	if ($RUN_MODE == 'cli')
		fputs(STDERR, "$s\n");
	else if (content_type() == 'text/plain')
		echo "\n$s\n";
	else if (content_type() == 'text/xml')
		echo "\n<debug>" . html($s) . "</debug>\n";
	else
		echo "\n<div style=\"", DEBUG_CSS, "\">", html($s), "</div>\n";
}


function _dump()
{
	$is_html = content_type() != 'text/plain';
	if ($is_html)
		echo '<pre style="', DEBUG_CSS, '">';
	call_user_func_array('var_dump',
		array_map(function ($x) use($is_html) { return $is_html && is_string($x) ? html($x) : $x; },
			func_get_args()));
	if ($is_html)
		echo '</pre>';
}


// --- SHORTCUTS ----------------------------------------------------------- //


function html($s)		{ return htmlspecialchars($s); }

function htmla($a)		{ return array_map(function ($x) { return html($x); }, $a); }

function jstr($s)		{ return '\'' . addcslashes($s, "/\\\'\n\r\t") . '\''; }

function sql_str($s)	{ return '\'' . addslashes($s) . '\''; }

function sql_str_array(array $a)
	{ return implode(',', array_map(function ($s) { return sql_str($s); }, $a)); }

function sql_like_str($s)
	{ return sprintf("'%s%%'", addcslashes($s, "\\\'%_")); }

function tesc($s)
	{ return addcslashes($s, "\\\n\r\t"); }

function strip_tags2($s)
	{ return strip_tags(preg_replace('/(<p|<li|<br|<div)/i', ' $1', $s)); }


class _GET extends stdClass
{
	function __get($name)
		{ return isset($_GET[$name]) ? $_GET[$name] : ''; }
	function __set($name, $value)
		{ $_GET[$name] = $value; }
	function to_url()
	{
		$a = [];
		foreach ($_GET as $k => $v)
			$v && $a[] = $k . '=' . urlencode($v);
		return implode('&', $a);
	}
}
class _GETI extends stdClass
{
	function __get($name)
		{ return isset($_GET[$name]) ? (int)$_GET[$name] : 0; }
}
$GET = new _GET;
$GETI = new _GETI;

class _POST extends stdClass
{
	function __get($name)
		{ return isset($_POST[$name]) ? $_POST[$name] : ''; }
}
class _POSTI extends stdClass
{
	function __get($name)
		{ return isset($_POST[$name]) ? (int)$_POST[$name] : 0; }
}
$POST = new _POST;
$POSTI = new _POSTI;


function form_submitted($form_name = 'form1')
	{ return isset($_POST['_f']) && explode(';', $_POST['_f'])[0] == $form_name; }


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

function http_referer_uri($restrict_uri = NULL)
{
	// TODO: also check $POST['_ret_uri']
	$url = http_referer();
	$prefix = http_proto() . '://' . http_host();
	if (substr($url, 0, strlen($prefix)) == $prefix)
	{
		$uri = substr($url, strlen($prefix));
		if (!$restrict_uri || substr($uri, 0, strlen($restrict_uri)) == $restrict_uri)
			return $uri;
	}
	return '';
}

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


function _date_diff($d1, $d2)
{
	return (!$d1 || !$d2) ?
		new DateInterval ('P0Y0DT0H0M') :
		date_diff(new DateTime($d1), new DateTime($d2));
}


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
	if (is_debug_mode() && ob_get_length())
		ob_flush(); // force printing of notices and diag messages, if any
	header("Location: $url");
	exit();
}

?>
