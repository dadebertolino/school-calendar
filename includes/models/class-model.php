<?php
namespace SchoolCalendar\Models;

defined('ABSPATH') || exit;

abstract class Model {
    protected static $table;
    protected static $primary_key = 'id';
    protected static $fillable = [];
    
    protected $attributes = [];
    protected $original = [];
    
    public function __construct($attributes = []) {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }
    
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . SC_TABLE_PREFIX . static::$table;
    }
    
    public function fill($attributes) {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable) || $key === static::$primary_key) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }
    
    public function __get($key) {
        return $this->attributes[$key] ?? null;
    }
    
    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }
    
    public function __isset($key) {
        return isset($this->attributes[$key]);
    }
    
    public function toArray() {
        return $this->attributes;
    }
    
    public static function find($id) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . static::table() . " WHERE " . static::$primary_key . " = %d",
            $id
        ), ARRAY_A);
        
        return $row ? new static($row) : null;
    }
    
    public static function findBy($field, $value) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . static::table() . " WHERE {$field} = %s LIMIT 1",
            $value
        ), ARRAY_A);
        
        return $row ? new static($row) : null;
    }
    
    public static function all($conditions = [], $order_by = null, $limit = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM " . static::table();
        $params = [];
        
        if (!empty($conditions)) {
            $where_parts = [];
            foreach ($conditions as $key => $value) {
                if (is_null($value)) {
                    $where_parts[] = "$key IS NULL";
                } else {
                    // Usa %d per interi, %s per stringhe
                    $placeholder = is_int($value) ? '%d' : '%s';
                    $where_parts[] = "$key = $placeholder";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where_parts);
        }
        
        if ($order_by) {
            $sql .= " ORDER BY $order_by";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new static($row), $rows);
    }
    
    public function save() {
        global $wpdb;
        
        $data = array_intersect_key($this->attributes, array_flip(static::$fillable));
        
        if (isset($this->attributes[static::$primary_key]) && $this->attributes[static::$primary_key]) {
            // Update
            $result = $wpdb->update(
                static::table(),
                $data,
                [static::$primary_key => $this->attributes[static::$primary_key]]
            );
        } else {
            // Insert
            $result = $wpdb->insert(static::table(), $data);
            if ($result) {
                $this->attributes[static::$primary_key] = $wpdb->insert_id;
            }
        }
        
        $this->original = $this->attributes;
        
        return $result !== false;
    }
    
    public function delete() {
        global $wpdb;
        
        if (!isset($this->attributes[static::$primary_key])) {
            return false;
        }
        
        return $wpdb->delete(
            static::table(),
            [static::$primary_key => $this->attributes[static::$primary_key]]
        ) !== false;
    }
    
    public static function query() {
        return new QueryBuilder(static::class);
    }
}

class QueryBuilder {
    private $model_class;
    private $wheres = [];
    private $params = [];
    private $order_by = [];
    private $limit;
    private $offset;
    
    public function __construct($model_class) {
        $this->model_class = $model_class;
    }
    
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = "$column $operator %s";
        $this->params[] = $value;
        
        return $this;
    }
    
    public function whereNull($column) {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }
    
    public function whereIn($column, array $values) {
        if (empty($values)) {
            $this->wheres[] = "1 = 0"; // Sempre falso
            return $this;
        }
        
        // Determina il tipo di placeholder
        $placeholders = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $placeholders[] = '%d';
            } else {
                $placeholders[] = '%s';
            }
            $this->params[] = $value;
        }
        
        $this->wheres[] = "$column IN (" . implode(',', $placeholders) . ")";
        
        return $this;
    }
    
    public function whereRaw($sql, $params = []) {
        $this->wheres[] = $sql;
        foreach ($params as $param) {
            $this->params[] = $param;
        }
        return $this;
    }
    
    public function whereBetween($column, $start, $end) {
        $this->wheres[] = "$column BETWEEN %s AND %s";
        $this->params[] = $start;
        $this->params[] = $end;
        
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC') {
        $this->order_by[] = "$column $direction";
        return $this;
    }
    
    public function limit($limit) {
        $this->limit = (int) $limit;
        return $this;
    }
    
    public function offset($offset) {
        $this->offset = (int) $offset;
        return $this;
    }
    
    public function get() {
        global $wpdb;
        
        $model_class = $this->model_class;
        $sql = "SELECT * FROM " . $model_class::table();
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        if (!empty($this->order_by)) {
            $sql .= " ORDER BY " . implode(', ', $this->order_by);
        }
        
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset) {
                $sql .= " OFFSET " . $this->offset;
            }
        }
        
        if (!empty($this->params)) {
            $sql = $wpdb->prepare($sql, ...$this->params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(fn($row) => new $model_class($row), $rows);
    }
    
    public function first() {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }
    
    public function count() {
        global $wpdb;
        
        $model_class = $this->model_class;
        $sql = "SELECT COUNT(*) FROM " . $model_class::table();
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        if (!empty($this->params)) {
            $sql = $wpdb->prepare($sql, ...$this->params);
        }
        
        return (int) $wpdb->get_var($sql);
    }
}
