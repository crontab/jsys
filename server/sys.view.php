<?  require_once 'sys.php' ?>
<?


class EForm extends Exception
{
	function __construct($field, $msg)
	{
		parent::__construct($msg);
		$this->field = $field;
	}
}


class generic extends stdClass
{
	function __construct()	{ }
	function setup()		{ }
	function get_descr()	{ return $this->name; }

	static function error($msg)
		{ throw new Exception($msg); }

	static function eform($field, $msg)
		{ throw new EForm($field, $msg); }
}


// --- FIELD/COLUMN DEFINITION -------------------------------------------- //


class TYPE
{
	const BOOL = 1;
	const INT = 2;
	const FLOAT = 3;
	const CSV = 4;		// only alphanumeric keywords allowed
	const INTARR = 5;
	const ARR = 6;
	const DATE = 7;
	const DATETIME = 8;
	const TEXT = 9;
	const OBJECT = 10;
}


class field_def extends generic
{
	var $namespace;	// table name in case of a SQL view
	var $ident;
	var $type;		// TYPE::xxx
	var $expr;		// SQL AS expression
	var $read_only;

	function __construct($namespace, $def, $type = 0, $expr = '', $read_only = false)
	{
		$this->namespace = $namespace;
		if ($type)
		{
			$this->ident = $def;
			$this->type = $type;
			$this->expr = $expr;
			$this->read_only = $read_only;
			return;
		}
		$len = strlen($def);
		$i = strpos($def, '/');
		if ($i === false)
			$i = $len;
		$this->ident = substr($def, 0, $i);
		$this->type = TYPE::TEXT;
		$this->expr = '';
		$this->read_only = false;
		$exit = false;
		$i++;
		while (!$exit && $i < $len)
		{
			switch($def[$i])
			{
				case 'i': $this->type = TYPE::INT; break;
				case 'f': $this->type = TYPE::FLOAT; break;
				case 'b': $this->type = TYPE::BOOL; break;
				case 't': $this->type = TYPE::TEXT; break;
				case 'C': $this->type = TYPE::CSV; break;
				case 'I': $this->type = TYPE::INTARR; break;
				case 'T': $this->type = TYPE::ARR; break;
				case 'd': $this->type = TYPE::DATE; break;
				case 'D': $this->type = TYPE::DATETIME; break;
				case 'r': $this->read_only = true; break;
				case '/': $exit = true; break;
				case 'o':
					if ($i + 1 < $len && $def[$i + 1] == ':')
					{
						$cls = substr($def, $i + 2);
						$j = strpos($cls, '/');
						if ($j !== false)
							$cls = substr($cls, 0, $j);
						$this->type = TYPE::OBJECT;
						$this->view_object = I($cls);
						$i += strlen($cls) + 1;
					}
					else
						internal('Invalid object type');
					$exit = true;
					break;
				default: internal('Unknown field attribute');
			}
			$i++;
		}
		if ($i < $len)
			$this->expr = substr($def, $i);
	}

	function get_expr()
		{ return $this->expr ?: (($this->namespace ? ($this->namespace . '.') : '') . $this->ident); }

	function get_column_expr()
		{ return $this->expr ? ($this->expr . ' AS ' . $this->ident)
			: (($this->namespace ? ($this->namespace . '.') : '') . $this->ident); }

	function empty_value()
	{
		switch ($this->type)
		{
			case TYPE::BOOL: return false;
			case TYPE::INT:
			case TYPE::FLOAT: return 0;
			case TYPE::CSV:
			case TYPE::INTARR:
			case TYPE::ARR: return [];
			case TYPE::DATE: return '0000-00-00';
			case TYPE::DATETIME: return '0000-00-00 00:00:00';
			case TYPE::OBJECT: return $this->view_object->empty_obj();
			default: return '';
		}
	}

	function is_empty_value($value)
	{
		switch ($this->type)
		{
			case TYPE::DATE:
			case TYPE::DATETIME: return is_empty_date($value);
			case TYPE::OBJECT:
				return is_null($value) || $this->view_object->is_empty_obj($value);
			default: return !$value;
		}
	}

	function typecast($value)
	{
		switch($this->type)
		{
			case TYPE::BOOL: return (bool)$value;
			case TYPE::INT: return (int)$value;
			case TYPE::FLOAT: return (float)$value;
			case TYPE::CSV:
				return is_string($value) ? csv_to_array($value) : notimpl(0x1001);
			case TYPE::INTARR:
				return is_string($value) ? csv_to_int_array($value) : notimpl(0x1002);
			case TYPE::ARR:
				return is_string($value) ? lines_to_array($value) : notimpl(0x1003);
			case TYPE::OBJECT:
				return is_string($value) ? $this->view_object->from_ini($value) : notimpl(0x1004);
			default: return (string)$value;
		}
	}

	function to_sql_expr($value)
	{
		switch ($this->type)
		{
			case TYPE::BOOL: return ($value > 0 ? 1 : 0);
			case TYPE::INT: return (int)$value;
			case TYPE::FLOAT: return (float)$value;
			case TYPE::CSV:
			case TYPE::INTARR: return sql_str(implode(',', $value));
			case TYPE::ARR: return sql_str(array_to_lines($value));
			case TYPE::OBJECT:
				return sql_str($this->view_object->to_ini($value));
			default: return sql_str($value);
		}
	}

	function to_str($value)
	{
		switch ($this->type)
		{
			case TYPE::BOOL: return (string)(int)$value;
			case TYPE::CSV:
			case TYPE::INTARR: return implode(',', $value);
			case TYPE::ARR: return array_to_lines($value);
			case TYPE::OBJECT:
				return $this->view_object->to_ini($value);
			default: return (string)$value;
		}
	}

	function from_request($prefix = '')
	{
		$name = $prefix . $this->ident;
		switch($this->type)
		{
			case TYPE::BOOL: return isset($_REQUEST[$name]) && (bool)$_REQUEST[$name];
			case TYPE::INT: return isset($_REQUEST[$name]) ? (int)$_REQUEST[$name] : 0;
			case TYPE::FLOAT: return isset($_REQUEST[$name]) ? (float)$_REQUEST[$name] : 0;
			case TYPE::INTARR:
				$a = isset($_REQUEST[$name]) ? $_REQUEST[$name] : [];
				array_cast_to_int($a);
				return $a;
			case TYPE::CSV:
			case TYPE::ARR: return isset($_REQUEST[$name]) ? $_REQUEST[$name] : [];
			case TYPE::OBJECT:
				return $this->view_object->from_request($name . '_');
			default: return isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
		}
	}
}


// --- GENERIC VIEW ------------------------------------------------------- //


class view extends generic
{
	var $item_class;
	var $fields = [];		// array of field_def objects

	function __construct($namespace, array $field_list)
	{
		parent::__construct();
		$this->item_class = 'generic';
		self::add_static_fields($namespace, $field_list);
	}

	function add_field(field_def $def)
		{ $this->fields[$def->ident] = $def; }

	function add_static_field($namespace, $def)
		{ $this->add_field(new field_def($namespace, $def)); }

	function add_static_fields($namespace, array $defs)
		{ foreach ($defs as $def)
			$this->add_static_field($namespace, $def); }

	function set_item_class($c)
		{ $this->item_class = $c; }

	function empty_obj()
	{
		$obj = new $this->item_class;
		foreach ($this->fields as $ident => $def)
			$obj->$ident = $def->empty_value();
		$obj->setup();
		return $obj;
	}

	function is_empty_obj(stdClass $obj)
	{
		foreach ($this->fields as $ident => $def)
			if (isset($obj->$ident) && !$def->is_empty_value($obj->$ident))
				return false;
		return true;
	}

	function from_mysqli_obj($obj)
	{
		if (is_null($obj))
			return $this->empty_obj();
		foreach ($this->fields as $ident => $def)
		{
			switch ($def->type)
			{
				case TYPE::BOOL:	$obj->$ident = (bool)$obj->$ident; break;
				case TYPE::INT:		$obj->$ident = (int)$obj->$ident; break;
				case TYPE::FLOAT:	$obj->$ident = (float)$obj->$ident; break;
				case TYPE::CSV:		$obj->$ident = csv_to_array($obj->$ident); break;
				case TYPE::INTARR:	$obj->$ident = csv_to_int_array($obj->$ident); break;
				case TYPE::ARR:		$obj->$ident = lines_to_array($obj->$ident); break;
				case TYPE::OBJECT:	$obj->$ident = $def->view_object->from_ini($obj->$ident); break;
				// default: $obj->$ident = (string)$obj->$ident; break;
			}
		}
		$obj->setup();
		return $obj;
	}

	function _from($func)
	{
		$obj = new $this->item_class;
		foreach ($this->fields as $ident => $def)
			$obj->$ident = $func($def, $ident);
		$obj->setup();
		return $obj;
	}

	function from_obj(stdClass $obj)
		{ return $this->_from(
			function ($def, $ident) use ($obj)
				{ return $def->typecast(isset($obj->$ident) ? $obj->$ident : ''); }); }

	function from_request($prefix = '')
		{ return $this->_from(
			function ($def, $ident) use($prefix)
				{ return $def->from_request($prefix); }); }

	function to_ini(stdClass $obj)
	{
		$s = '';
		foreach ($this->fields as $name => $def)
			if (isset($obj->$name) && !$def->read_only)
			{
				$value = $obj->$name;
				if (!$def->is_empty_value($value))
					$s .= ($s ? "\n" : '') . $name . '='
						. addcslashes($def->to_str($value), "\\\n");
			}
		return $s;
	}

	function from_ini($s)
	{
		$obj = $this->empty_obj();
		$lines = explode("\n", $s);
		foreach ($lines as $line)
		{
			$t = explode('=', trim($line), 2);
			if (count($t) != 2)
				continue;
			$name = $t[0];
			if (!isset($this->fields[$name]))
				continue;
			$obj->$name = $this->fields[$name]->typecast(stripcslashes($t[1]));
		}
		$obj->setup();
		return $obj;
	}
}


function I($class_name)
{
	static $cache = [];
	return isset($cache[$class_name]) ? $cache[$class_name] : ($cache[$class_name] = new $class_name);
}

?>
