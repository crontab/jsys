<? require_once 'sys.auth.php' ?>
<?

if ($USER_ID == 0)
{
	if ($RUN_MODE == 'web')
	{
		setcookie(REDIRECT_COOKIE_NAME, http_request_uri(), 0, '/');
		redirect('/signin');
	}
	else
		fatal('Authentication required');
}

?>
