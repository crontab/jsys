<? require_once 'sys.session.php' ?>
<?

const REDIRECT_COOKIE_NAME = 'HM_REDIR';

if ($RUN_MODE == 'web')
	session_begin();

else if ($RUN_MODE == 'data')
{
	$session_id = '';
	if (isset($_COOKIE[SESSION_COOKIE_NAME]))
		$session_id = $_COOKIE[SESSION_COOKIE_NAME];
	else if ($DEBUG_MODE)
		$session_id = get_str(SESSION_URI_PARAM_NAME);
	session_retrieve($session_id);
}

// In CLI mode $USER_ID can be set manually AFTER including this script

if ($USER_ID)
{
	$_user = $DB->first_obj(sprintf('SELECT id, name FROM users WHERE id=%d AND password<>""', $USER_ID));
	if ($_user)
	{
		$USER_ID = (int)$_user->id;
		$USER_NAME = $_user->name;
	}
	else
	{
		$USER_ID = 0;
		$USER_NAME = '';
	}
	unset($_user);
}


function my_user_id()		{ global $USER_ID; return $USER_ID; }
function my_descr()			{ global $USER_NAME, $USER_ID; return $USER_NAME ?: "User$USER_ID"; }
function can_admin()		{ global $USER_ID; return $USER_ID == 1; }


function require_unique_form()
{
	global $SESSION;
	list($f, $uid) = explode(';', $_REQUEST['_f']);
	$key = $_SERVER['REQUEST_URI'];
	if (!$key || !$uid || session_str($key) == $uid)
		throw new Exception('This form has already been submitted.');
	session_set_str($key, $uid);
}



?>
