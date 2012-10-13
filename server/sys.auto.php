<? require_once 'sys.db.php' ?>
<?

function __autoload($class_name)
{
	global $_HTDOCS;
	require_once "$_HTDOCS/lib/$class_name.php";
}

?>
