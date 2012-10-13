<? require_once 'sys.db.php' ?>
<?

/*

	CREATE TABLE sessions (
		id VARCHAR(32) CHARACTER SET ascii NOT NULL DEFAULT '',
		created TIMESTAMP NOT NULL DEFAULT 0,
		user_id INT NOT NULL DEFAULT 0,
		ip_address VARCHAR(15) CHARACTER SET ascii NOT NULL DEFAULT '',
		data TEXT NOT NULL DEFAULT '',
		UNIQUE KEY id (id),
		KEY created (created)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

*/

const SESSION_COOKIE_NAME = 'HM_SID';
const SESSION_URI_PARAM_NAME = '_HM_SID';
const SESSION_COOKIE_PATH = '/';
const SESSION_EXPIRE = 31536000;			// 86400 * 365 -- 1 year for keeping the session alive
const SESSION_SANITIZE_FACTOR = 50;			// Delete expired records every 50 calls to session_begin()


// Session types
const SESSION_TEMPORARY = 0;
const SESSION_PERSISTENT = 1;
const SESSION_DONT_KNOW = -1;	// or rather, "don't care"


// Globals
$SESSION_ID = '';
$USER_ID = 0;
$SESSION = [];
$_save_session_data = '';
$_save_user_id = 0;
$_session_began = false;


function _is_hash_str($s)
	{ return is_string($s) && preg_match('/[0-9a-f]{32}/', $s); }


function _session_sanitize()
{
	global $DB;
	if (mt_rand(0, SESSION_SANITIZE_FACTOR) == 0)
		$DB->query('DELETE FROM sessions WHERE created < DATE_SUB(NOW(), INTERVAL ' . 
			SESSION_EXPIRE . ' SECOND)');
}


function session_begin($persistent = SESSION_DONT_KNOW)
{
	global $SESSION_ID, $USER_ID, $SESSION;
	global $_save_session_data, $_save_user_id;
	global $_session_began;
	if ($_session_began)
		return;

	_session_sanitize();

	if (isset($_COOKIE[SESSION_COOKIE_NAME]) && _is_hash_str($_COOKIE[SESSION_COOKIE_NAME]))
	{
		session_retrieve($_COOKIE[SESSION_COOKIE_NAME]);
	}

	else if ($persistent != SESSION_DONT_KNOW)
	{
		// Construct a new name for the session id; then try to set the cookie
		$SESSION_ID = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT'] . microtime() . 'toot');
		$cookie_expire = $persistent == SESSION_PERSISTENT ? time() + SESSION_EXPIRE : NULL;
		setcookie(SESSION_COOKIE_NAME, $SESSION_ID, $cookie_expire, SESSION_COOKIE_PATH) or die("Couldn't set cookie");
	}

	register_shutdown_function('_session_end');

	$_session_began = true;
}


function session_retrieve($session_id)
{
	global $DB;
	global $SESSION_ID, $USER_ID, $SESSION;
	global $_save_session_data, $_save_user_id;

	if ($session_id == '' ||
			!_is_hash_str($session_id))	   // protect against "deleted", which appears to be returned by IE
		return false;

	$SESSION_ID = $session_id;

	$obj = $DB->first_obj(sprintf('SELECT user_id, ip_address, data FROM sessions WHERE id=%s', sql_str($session_id)));
	if ($obj)
	{
		$_save_user_id = $USER_ID = $obj->user_id;
		if ($obj->data != '')
		{
			$_save_session_data = $obj->data;
			$SESSION = json_decode($_save_session_data, true);
		}
		else
			$_save_session_data = '';
	}

	return true;
}


function session_forget($persistent = SESSION_DONT_KNOW)
{
	global $DB;
	global $SESSION_ID, $USER_ID, $SESSION;
	global $_save_session_data, $_save_user_id;
	global $_session_began;

	if (!$_session_began)
		session_begin($persistent);

	if ($SESSION_ID != '')
	{
		// Delete the cookie from user's machine
		setcookie(SESSION_COOKIE_NAME, '', time() - 86400, SESSION_COOKIE_PATH)
			or die('Could not delete cookie');
		// Remove session data from the database
		$DB->query(sprintf("DELETE FROM sessions WHERE id=%s", sql_str($SESSION_ID)));
	}

	$SESSION_ID = '';
	$USER_ID = 0;
	$SESSION = [];

	// Prevent _session_end() from being called at the end
	$_session_began = false;
}


function _session_end()
{
	global $DB;
	global $SESSION_ID, $USER_ID, $SESSION;
	global $_save_session_data, $_save_user_id;
	global $_session_began;

	if (!$_session_began || $SESSION_ID == '')
		return;

	if (is_array($SESSION) && count($SESSION) > 0)
		$SESSION = array_filter($SESSION, function($var) { return isset($var) && !is_null($var); });

	if (is_array($SESSION) && count($SESSION) > 0)
		$data = json_encode($SESSION);
	else
		$data = '';

	if (_is_hash_str($SESSION_ID) && ($data != $_save_session_data || $USER_ID != $_save_user_id))
	{
		$assigns = sprintf('user_id=%d, ip_address=%s, data=%s',
			$USER_ID, sql_str($_SERVER['REMOTE_ADDR']), sql_str($data));
		$DB->query(sprintf('INSERT INTO sessions SET id=%s, created=NOW(), %s ON DUPLICATE KEY UPDATE %s',
			sql_str($SESSION_ID), $assigns, $assigns));
	}

	$_session_began = false;
}


function session_str($name, $default = '')
{
	global $SESSION;
	if (isset($SESSION[$name]))
		return $SESSION[$name];
	return $default;
}


function session_int($name, $default = 0)
{
	global $SESSION;
	if (isset($SESSION[$name]))
		return (int)$SESSION[$name];
	return (int)$default;
}


function session_set_str($name, $str, $default = '')
{
	global $SESSION;
	if ($str == $default)
		unset($SESSION[$name]);
	else
		$SESSION[$name] = $str;
}


function session_set_int($name, $int, $default = 0)
{
	global $SESSION;
	$int = (int)$int;
	if ($int == $default)
		unset($SESSION[$name]);
	else
		$SESSION[$name] = $int;
}

?>
