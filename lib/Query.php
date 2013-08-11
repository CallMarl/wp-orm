<?php

namespace WordPress\ORM;

/**
 * Progressively build up a query to get results using an easy to understand
 * DSL.
 *
 * @author Brandon Wamboldt <brandon.wamboldt@gmail.com>
 */
class Query
{
	/**
	 * @var string
	 */
	const ORDER_ASCENDING = 'ASC';

	/**
	 * @var string
	 */
	const ORDER_DESCENDING = 'DESC';

	/**
	 * @var integer
	 */
	protected $limit = 0;

	/**
	 * @var integer
	 */
	protected $offset = 0;

	/**
	 * @var array
	 */
	protected $where = array();

	/**
	 * @var string
	 */
	protected $sort_by = 'id';

	/**
	 * @var string
	 */
	protected $order = 'ASC';

	/**
	 * @var string|null
	 */
	protected $search_term = null;

	/**
	 * @var array
	 */
	protected $search_fields = array();

	/**
	 * @var string
	 */
	protected $model;

	/**
	 * @var string
	 */
	protected $primary_key;

	/**
	 * @param string $model
	 */
	public function __construct($model)
	{
		$this->model = $model;
	}

	/**
	 * Set the fields to include in the search.
	 *
	 * @param  array $fields
	 */
	public function set_searchable_fields(array $fields)
	{
		$this->search_fields = $fields;
	}

	/**
	 * Set the primary key column.
	 *
	 * @param string $primary_key
	 */
	public function set_primary_key($primary_key)
	{
		$this->primary_key = $primary_key;
		$this->sort_by     = $primary_key;
	}

	/**
	 * Set the maximum number of results to return at once.
	 *
	 * @param  integer $limit
	 * @return self
	 */
	public function limit($limit)
	{
		$this->limit = (int) $limit;

		return $this;
	}

	/**
	 * Set the offset to use when calculating results.
	 *
	 * @param  integer $offset
	 * @return self
	 */
	public function offset($offset)
	{
		$this->offset = (int) $offset;

		return $this;
	}

	/**
	 * Set the column we should sort by.
	 *
	 * @param  string $sort_by
	 * @return self
	 */
	public function sort_by($sort_by)
	{
		$this->sort_by = $sort_by;

		return $this;
	}

	/**
	 * Set the order we should sort by.
	 *
	 * @param  string $order
	 * @return self
	 */
	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	/**
	 * Add a `=` clause to the search query.
	 *
	 * @param  string $column
	 * @param  string $value
	 * @return self
	 */
	public function where($column, $value)
	{
		$this->where[] = ['type' => 'where', 'column' => $column, 'value' => $value];

		return $this;
	}

	/**
	 * Add a `!=` clause to the search query.
	 *
	 * @param  string $column
	 * @param  string $value
	 * @return self
	 */
	public function where_not($column, $value)
	{
		$this->where[] = ['type' => 'not', 'column' => $column, 'value' => $value];

		return $this;
	}

	/**
	 * Add a `LIKE` clause to the search query.
	 *
	 * @param  string $column
	 * @param  string $value
	 * @return self
	 */
	public function where_like($column, $value)
	{
		$this->where[] = ['type' => 'like', 'column' => $column, 'value' => $value];

		return $this;
	}

	/**
	 * Add a `NOT LIKE` clause to the search query.
	 *
	 * @param  string $column
	 * @param  string $value
	 * @return self
	 */
	public function where_not_like($column, $value)
	{
		$this->where[] = ['type' => 'not_like', 'column' => $column, 'value' => $value];

		return $this;
	}

	/**
	 * Add an `IN` clause to the search query.
	 *
	 * @param  string $column
	 * @param  array  $value
	 * @return self
	 */
	public function where_in($column, array $in)
	{
		$this->where[] = ['type' => 'in', 'column' => $column, 'value' => $in];

		return $this;
	}

	/**
	 * Add a `NOT IN` clause to the search query.
	 *
	 * @param  string $column
	 * @param  array  $value
	 * @return self
	 */
	public function where_not_in($column, array $not_in)
	{
		$this->where[] = ['type' => 'not_in', 'column' => $column, 'value' => $not_in];

		return $this;
	}

	/**
	 * Add an OR statement to the where clause (e.g. (var = foo OR var = bar OR
	 * var = baz)).
	 *
	 * @param  array $where
	 * @return self
	 */
	public function where_any(array $where)
	{
		$this->where[] = ['type' => 'any', 'where' => $where];

		return $this;
	}

	/**
	 * Add an AND statement to the where clause (e.g. (var1 = foo AND var2 = bar
	 * AND var3 = baz)).
	 *
	 * @param  array $where
	 * @return self
	 */
	public function where_all(array $where)
	{
		$this->where[] = ['type' => 'all', 'where' => $where];

		return $this;
	}

	/**
	 * Get models where any of the designated fields match the given value.
	 *
	 * @param  string $search_term
	 * @return self
	 */
	public function search($search_term)
	{
		$this->search_term = $search_term;

		return $this;
	}

	/**
	 * Runs the same query as find, but with no limit and don't retrieve the
	 * results, just the total items found.
	 *
	 * @return integer
	 */
	public function total_count()
	{
		return $this->find(true);
	}

	/**
	 * Compose & execute our query.
	 *
	 * @return array
	 */
	public function find($only_count = false)
	{
		global $wpdb;

		$model  = $this->model;
		$table  = $model::get_table();
		$where  = '';
		$order  = '';
		$limit  = '';
		$offset = '';

		// Search
		if (!empty($this->search_term)) {
			$where .= ' AND (';

			foreach ($this->search_fields as $field) {
				$where .= '`' . $field . '` LIKE "%' . esc_sql($this->search_term) . '%" OR ';
			}

			$where = substr($where, 0, -4) . ')';
		}

		// Where
		foreach ($this->where as $q) {
			// where
			if ($q['type'] == 'where') {
				$where .= ' AND `' . $q['column'] . '` = "' . esc_sql($q['value']) . '"';
			}

			// where_not
			elseif ($q['type'] == 'not') {
				$where .= ' AND `' . $q['column'] . '` != "' . esc_sql($q['value']) . '"';
			}

			// where_like
			elseif ($q['type'] == 'like') {
				$where .= ' AND `' . $q['column'] . '` LIKE "' . esc_sql($q['value']) . '"';
			}

			// where_not_like
			elseif ($q['type'] == 'not_like') {
				$where .= ' AND `' . $q['column'] . '` NOT LIKE "' . esc_sql($q['value']) . '"';
			}

			// where_any
			elseif ($q['type'] == 'any') {
				$where .= ' AND (';

				foreach ($q['where'] as $column => $value) {
					$where .= '`' . $column . '` = "' . esc_sql($value) . '" OR ';
				}

				$where = substr($where, 0, -5) . ')';
			}

			// where_all
			elseif ($q['type'] == 'all') {
				$where .= ' AND (';

				foreach ($q['where'] as $column => $value) {
					$where .= '`' . $column . '` = "' . esc_sql($value) . '" AND ';
				}

				$where = substr($where, 0, -5) . ')';
			}
		}

		// Finish where clause
		if (!empty($where)) {
			$where = ' WHERE ' . substr($where, 5);
		}

		// Order
		$order = ' ORDER BY `' . $this->sort_by . '` ' . $this->order;

		// Limit
		if ($this->limit > 0) {
			$limit = ' LIMIT ' . $this->limit;
		}

		// Offset
		if ($this->offset > 0) {
			$offset = ' OFFSET ' . $this->offset;
		}

		// Query
		if ($only_count) {
			$query = "SELECT COUNT(*) FROM `{$table}`{$where}";

			return (int) $wpdb->get_var($query);
		}

		$query = "SELECT * FROM `{$table}`{$where}{$order}{$limit}{$offset}";

		$results = $wpdb->get_results($query);

		if ($results) {
			foreach ($results as $index => $result) {
				$results[$index] = $model::create((array) $result);
			}
		}

		return $results;
	}
}
