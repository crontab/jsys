<?  require_once 'sys.view.php' ?>
<?

if (!defined('SQL_DEBUG'))
	define('SQL_DEBUG', false);


class ESql extends Exception { }

class ESqlDuplicate extends ESql { }


class database extends mysqli
{

	function __construct($config_name = 'database')
	{
		global $DEBUG_MODE;
		if (!preg_match('|^(.+):(.+)@(.+)/(.+)$|', cfg_read_value($config_name), $m))
			fatal($DEBUG_MODE ? "Invalid connection argument: $s" : 'Internal 1022');
		$username = $m[1];
		$password = $m[2];
		$hostname = $m[3];
		$dbname = $m[4];
		parent::__construct($hostname, $username, $password);
		if (mysqli_connect_errno())
			fatal($DEBUG_MODE ? mysqli_connect_error() : 'Internal 1013');
		if (self::query('SET NAMES utf8') === false)
			fatal($DEBUG_MODE ? $this->error : 'Internal 1001');
		if (!parent::select_db($dbname))
			fatal($DEBUG_MODE ? $this->error : 'Internal 1002');
	}

	function query_nofail($query)
	{
		if (SQL_DEBUG)
			debug_dump($query);
		return parent::query($query);
	}

	function query($query)
	{
		$result = self::query_nofail($query);
		if ($result === false)
			self::fail();
		return $result;
	}

	function lock($params)
		{ self::query('LOCK TABLES ' . $params); }

	function unlock()
		{ self::query_nofail('UNLOCK TABLES'); }

	function update($query)
	{
		self::query_nofail($query);
		if ($this->errno == 1062)
			throw new ESqlDuplicate($this->error);
		else if ($this->errno)
			self::fail();
	}

	function update_get_count($query)
	{
		self::update($query);
		return $this->affected_rows;
	}

	function insert($query)
		{ self::update($query); }

	function insert_get_id($query)
	{
		self::update($query);
		return $this->insert_id;
	}

	function delete($query)
		{ self::query($query); }

	function delete_get_count($query)
	{
		self::delete($query);
		return $this->affected_rows;
	}

	function num_rows($res)
		{ return $res->num_rows; }

	function first_obj($query, $class_name = 'stdClass')
	{
		$result = self::begin($query);
		$obj = $result->fetch_object($class_name);
		self::end($result);
		return $obj ?: NULL;
	}

	function first_value($query)
	{
		$result = self::begin($query);
		$value = self::next_value($result);
		self::end($result);
		return $value;
	}

	function begin($query)
		{ return self::query($query); }

	function next($res, $class_name = 'stdClass')
		{ return $res->fetch_object($class_name); }

	function next_value($result)
	{
		$row = $result->fetch_row();
		return $row ? $row[0] : NULL;
	}

	function end($res)
		{ $res->free(); }

	function fail()
	{
		global $DEBUG_MODE;
		fatal($DEBUG_MODE ? $this->error : 'Internal 1005');
	}

}


// Master (default) database, always connect to it

$DB = new database();


// ------------------------------------------------------------------------ //
// --- SQL VIEW ----------------------------------------------------------- //
// ------------------------------------------------------------------------ //


class _join_info
{
	var $table_name;
	var $condition;
	var $real_name;

	function __construct($table_name, $condition, $real_name = '')
	{
		$this->table_name = $table_name;
		$this->condition = $condition;
		$this->real_name = $real_name;
	}

	function get_factor()
		{ return ($this->real_name ? ($this->real_name . ' ') : '')
			. $this->table_name . ' ON ' . $this->condition; }
}


// example field list: ['id/i', 'title', 'created/D', 'section_type/ir/section_type+0']

class sql_view extends view
{
	const SEARCH_MAX = 50;
	const PAGE_SIZE = 10;

	var $db;
	var $table_name;
	var $primary_key_def;	// primary key for find_by_id(), update(), delete(), updown()
	var $value_def;			// value field for begin_search() and begin_page()
	var $left_joins;

	var $sql_result;		// all three set by begin()
	var $total_rows;
	var $num_rows;

	var $_cached_objects;
	var $_cached_values;

	function __construct($table_name, array $field_list)
	{
		global $DB;
		parent::__construct($table_name, $field_list);
		$this->db = $DB;
		$this->table_name = $table_name;
		$this->key_def = $this->fields ? reset($this->fields) : NULL;
		$this->value_def = $this->fields ? next($this->fields) : NULL;
	}

	function __destruct()
		{ if ($this->sql_result) $this->end(); }

	function set_key_name($key_name)
		{ $this->key_def = $this->fields[$key_name]; }

	function set_value_name($value_name)
		{ $this->value_def = $this->fields[$value_name]; }

	function add_left_join($table_name, $condition, array $field_list, $real_name = '')
	{
		$this->left_joins[] = new _join_info($table_name, $condition, $real_name);
		$this->add_static_fields($table_name, $field_list);
	}

	function find($where, $order_by = NULL, $limit = '1')
	{
		$query = sprintf('SELECT %s FROM %s WHERE %s %s LIMIT %s',
			$this->_columns(), $this->_table_names(), $where,
			$order_by ? 'ORDER BY ' . $order_by : '',
			$limit);
		return $this->from_mysqli_obj($this->db->first_obj($query, $this->item_class));
	}

	function find_by_id($key, $where = NULL)
		{ return $this->find($this->_comparison($this->key_def, $key) .
			($where ? " AND ($where)" : '')); }

	function find_value_by_id($key)
	{
		$query = sprintf('SELECT %s FROM %s WHERE %s',
			$this->value_def->get_column_expr(), $this->table_name,
			$this->_comparison($this->key_def, $key));
		return $this->value_def->typecast($this->db->first_value($query));
	}

	function get_by_id($id)
		{ return isset($this->_cached_objects[$id]) ?
			$this->_cached_objects[$id] :
			($this->_cached_objects[$id] = $this->find_by_id($id)); }

	function get_value_by_id($id)
		{ return isset($this->_cached_values[$id]) ?
			$this->_cached_values[$id] :
			($this->_cached_values[$id] = $this->find_value_by_id($id)); }

	function begin($where = '', $grp_order = '', $offset = 0, $row_count = -1, $calc_total_rows = true)
	{
		$this->end();
		if ($row_count >= 0 && $calc_total_rows)
			$this->total_rows = $this->db->first_value(sprintf('SELECT COUNT(0) FROM %s WHERE %s',
				$this->_table_names(), $where ?: '1'));
		if ($row_count != 0)
		{
			$query = sprintf('SELECT %s FROM %s WHERE %s',
				$this->_columns(), $this->_table_names(), $where ?: '1');
			if (is_array($grp_order))
				$query .= ' GROUP BY ' . $grp_order[0] . ($grp_order[1] ? ' ORDER BY ' . $order : '');
			else if ($grp_order)
				$query .= ' ORDER BY ' . $grp_order;
			if ($row_count > 0)
				$query .= sprintf(' LIMIT %d,%d', $offset, $row_count);
			$this->sql_result = $this->db->begin($query);
			if ($row_count < 0)
				$this->total_rows = $this->db->num_rows($this->sql_result);
		}
		$this->num_rows = $this->db->num_rows($this->sql_result);
	}

	function next()
	{
		$obj = $this->db->next($this->sql_result, $this->item_class);
		if (!$obj)
			return false;
		return $this->from_mysqli_obj($obj);
	}

	function end()
	{
		if (!is_null($this->sql_result))
		{
			$this->db->end($this->sql_result);
			$this->sql_result = NULL;
			$this->total_rows = 0;
			$this->num_rows = 0;
		}
	}

	function all($where = '', $grp_order = '')
	{
		$a = [];
		$this->begin($where, $grp_order);
		$key_name = $this->key_def->ident;
		while ($o = $this->next())
			$a[$o->$key_name] = $o;
		$this->end();
		return $a;
	}

	function all_values($where = '')
	{
		$a = [];
		if (is_array($where))
			$where = $where ?
				sprintf('%s IN (%s)', $this->key_def->get_expr(), sql_str_array($where)) : '0';
		$this->begin($where);
		$key_name = $this->key_def->ident;
		$value_name = $this->value_def->ident;
		while ($o = $this->next())
			$a[$o->$key_name] = $o->$value_name;
		$this->end();
		return $a;
	}

	function begin_search($q, $cond = '')
	{
		$value_expr = $this->value_def->get_expr();
		$where = '1';
		if ($q)
			$where .= sprintf(' AND %s LIKE %s', $value_expr, sql_like_str($q));
		if ($cond)
			$where .= ' AND (' . $cond . ')';
		if (isset($this->fields['is_deleted']))
			$where .= sprintf(' AND NOT %s', $this->fields['is_deleted']->get_expr());
		return $this->begin(
			$where, $q ? '' : sprintf('%s ASC', $value_expr),
			0, self::SEARCH_MAX, false);
	}

	function begin_page($q, $offset, $row_count, $cond = '', $desc = false)
	{
		$key_expr = $this->key_def->get_expr();
		$value_expr = $this->value_def->get_expr();
		$where = '1';
		if ($q)
			$where .= sprintf(' AND (%s=%s OR MATCH (%s) AGAINST (%s IN BOOLEAN MODE))',
				$key_expr, sql_str($q), $value_expr, sql_str($q));
		if ($cond)
			$where .= ' AND (' . $cond . ')';
		return $this->begin(
			$where, $q ? '' : sprintf($desc ? '%s DESC' : '%s ASC', $key_expr),
			$offset, $row_count == -1 ? self::SEARCH_MAX : $row_count, false);
	}

	function insert($values)
		{ return $this->db->insert_get_id(sprintf('INSERT INTO %s SET %s',
			$this->table_name, $this->_assignments($values))); }

	function replace($values)
		{ return $this->db->insert_get_id(sprintf('REPLACE INTO %s SET %s',
			$this->table_name, $this->_assignments($values))); }

	function insert_many(array $array)
		{ foreach ($array as $values) $this->insert($values); }

	function update($key, $values, $where = '')
		{ return $this->db->update_get_count(sprintf('UPDATE %s SET %s WHERE %s AND (%s)',
			$this->table_name,
			$this->_assignments($values),
			$this->_comparison($this->key_def, $key),
			$where ?: '1')); }

	function update_or_new(&$key, $values)
	{
		if ($key)
			$this->update($key, $values);
		else
			$key = $this->insert($values);
	}

	function delete($key, $where = '')
		{ return $this->db->delete_get_count(sprintf('DELETE FROM %s WHERE %s AND (%s)',
			$this->table_name,
			$this->_comparison($this->key_def, $key),
			$where ?: '1')); }

	function swap($a, $b)
	{
		$this->db->lock($this->table_name . ' WRITE');
		$r = $this->db->update_get_count(sprintf('UPDATE %s SET order_id=%d WHERE order_id=%d',
			$this->table_name, 0, $a));
		$r += $this->db->update_get_count(sprintf('UPDATE %s SET order_id=%d WHERE order_id=%d',
			$this->table_name, $a, $b));
		$r += $this->db->update_get_count(sprintf('UPDATE %s SET order_id=%d WHERE order_id=%d',
			$this->table_name, $b, 0));
		$this->db->unlock();
		return $r;
	}

	function _assignment(field_def $def, $value)
		{ return $def->get_expr() . '=' . $def->to_sql_expr($value); }

	function _inset(field_def $def, $values)
		{ return !$values ? '0' :
			($def->get_expr() . ' IN (' .
				implode(',', array_map(function ($i) use($def)
					{ return $def->to_sql_expr($i); }, $values)) . ')'); }

	function _comparison(field_def $def, $v)
		{ return is_array($v) ? $this->_inset($def, $v) : $this->_assignment($def, $v); }

	function _assignments($values)
	{
		$v = [];
		$is_obj = is_object($values);
		foreach ($this->fields as $ident => $def)
		{
			if ($def->read_only) continue;
			if ($is_obj)
			{
				if (!isset($values->$ident)) continue;
				$value = $values->$ident;
			}
			else
			{
				if (!isset($values[$ident])) continue;
				$value = $values[$ident];
			}
			$v[] = $this->_assignment($def, $value);
		}
		return implode(', ', $v);
	}

	function _columns()
		{ return implode(', ', array_map(function ($i)
			{ return $i->get_column_expr(); }, $this->fields)); }

	function _table_names()
	{
		$s = $this->table_name;
		if ($this->left_joins)
			foreach ($this->left_joins as $j)
				$s .= ' LEFT JOIN ' . $j->get_factor();
		return $s;
	}
}

?>
