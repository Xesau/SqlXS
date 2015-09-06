<?php

/**
 * SqlXS Query Builder
 *
 * @package SqlXS
 * @author Xesau
 */

namespace Xesau\SqlXS;

use PDO, PDOException;
use Exception, DomainException, RangeException, UnexpectedValueException;

/**
 * Query Builder component from SqlXS
 * Can be used individually
 */
class QueryBuilder
{
    // Query types
    const SELECT = 0;
    const UPDATE = 1;
    const DELETE = 2;

    // Where types
    const EQ   = 0;
    const NEQ  = 1;
    const LT   = 2;
    const GT   = 3;
    const IN   = 4;
    const LTEQ = 5;
    const GTEQ = 6;
    const REFS = 7;

    /**
     * @var int $type The query type
     * @var array[] $orders The order fields
     * @var array[] $wheres The set of where rules
     * @var array $fields The fields for the UPDATE or SELECT queries.
     * @var int $skip The amount of rows skipped.
     * @var int $limit The maximum amount of rows affected by the query.
     */
    protected $type   = 0;
    protected $orders = array();
    protected $wheres = array();
    protected $fields = array();
    protected $skip   = null;
    protected $limit  = null;

    /**
     * @var $pdo The PDO used by the QueryBuilder
     */
    protected static $pdo;

    /**
     * Sets the PDO instance the builder uses
     *
     * @param PDO $pdo The PDO
     */
    public static function setPDO(PDO $pdo)
    {
        self::$pdo = $pdo;
    }

    /**
     * Constructs a new query builder
     *
     * @param int $type The type of query
     * @param string $table The name of the table to perform actions on
     * @param array $fields The fields, used by SELECT and UPDATE
     */
    public function __construct($type, $table, array $fields = array())
    {
        if ($type < 0 && $type > 2)
            throw new DomainException('Type #'. striptags($type) . ' is no valid Query type.');

        $this->type = $type;
        $this->table = $table;
        $this->fields = $fields;
    }

    /**
     * Adds a field to the order list, with sorting method DESCENDING
     *
     * @param string $field The name of the field to sort with
     * @return $this The query object
     */
    public function asc($field) {
        $this->orders [] = new OrderRule($field, false);
        return $this;
    }

    /**
     * Adds a field to the order list, with sorting method DESCENDING
     *
     * @param string $field The name of the field to sort with
     * @return $this The query object
     */
    public function desc($field) {
        $this->orders [] = new OrderRule($field, true);
        return $this;
    }

    /**
     * Adds a condition
     *
     * @param string $field The field name
     * @param int $comparator The comparator (QueryBuilder::...)
     * @param mixed $value The value
     * @return $this The QueryBuilder
     */
    public function where($field, $comparator, $value)
    {
        $this->wheres[] = new WhereCondition(false, $field, $comparator, $value);
        return $this;
    }

    /**
     * Adds a condition for when the previous condition fails
     *
     * @param string $field The field name
     * @param int $comparator The comparator (QueryBuilder::...)
     * @param mixed $value The value
     * @return $this The QueryBuilder
     */
    public function whereOr($field, $comparator, $value)
    {
        $this->wheres[] = new WhereCondition(true, $field, $comparator, $value);
        return $this;
    }

    /**
     * Generates the query
     *
     * @return string The query
     */
    public function __toString()
    {
        switch ($this->type)
        {
            case self::SELECT:
                if (!count($this->fields))
                    throw new UnexpectedValueException('There are no fields provided to select.');
                $query = 'SELECT ';

                // Add the fields that must be selected
                $fields = array();
                foreach ($this->fields as $field)
                    $fields[] = self::fieldName($field);

                $query .= implode(', ', $fields);
                unset($fields);

                // Add the FROM part
                $query .= ' FROM '. self::tableName($this->table);

                // Add the WHERE part
                $query .= self::generateWhere($this->wheres);

                // Add the ORDER BY part
                $query .= self::generateOrder($this->orders);

                // Add the LIMIT part
                if ($this->skip !== null) {
                    $query .= ' LIMIT '. $this->skip .', '. ($this->limit === null ? '18446744073709551615' : $this->limit);
                } elseif ($this->limit !== null) {
                    $query .= ' LIMIT ' . $this->limit;
                }

                break;
            case self::UPDATE:
                $query = 'UPDATE '. self::tableName($this->table) .' SET';

                // Add the fields and their new values
                $first = true;
                foreach ($this->fields as $field => $value) {
                    $query .= ($first ? ' ' : ', '). self::fieldName($field) .' = '. self::$pdo->quote($value);
                    $first = false;
                }

                // Add the WHERE part
                $query .= self::generateWhere($this->wheres);

                // Add the ORDER BY part
                $query .= self::generateOrder($this->orders);

                // Add the LIMIT part
                if ($this->skip !== null) {
                    $query .= ' LIMIT '. $this->skip .', '. ($this->limit === null ? '18446744073709551615' : $this->limit);
                } elseif ($this->limit !== null) {
                    $query .= ' LIMIT ' . $this->limit;
                }
                break;
            case self::DELETE:
                $query = 'DELETE FROM '. self::tableName($this->table) . '';

                // Add WHERE part
                $query .= self::generateWhere($this->wheres);

                // Add the ORDER BY part
                $query .= self::generateOrder($this->orders);

                // Add the LIMIT part
                if ($this->skip !== null) {
                    $query .= ' LIMIT '. $this->skip .', '. ($this->limit === null ? '18446744073709551615' : $this->limit);
                } elseif ($this->limit !== null) {
                    $query .= ' LIMIT ' . $this->limit;
                }

                break;
            case self::COUNT:
                $query = 'SELECT COUNT(*) FROM '. self::tableName($this->table);

                // Add the WHERE part
                $query .= self::generateWhere($this->wheres);

                // Add the ORDER BY part
                $query .= self::generateOrder($this->orders);

                // Add the lIMIT part
                $query .= ' LIMIT 1';
                break;
            default:
                throw new DomainException('Unknown query type #'. $this->type .'.');
                break;
        }
        return $query;
    }

    /**
     * Sets the amount of rows skipped
     *
     * @param int $amount The amount of rows
     * @return $this The QueryBuilder
     */
    public function skip($amount)
    {
        $this->skip = intval($amount);
        return $this;
    }

    /**
     * Sets the maximum amount of rows this query will affect
     *
     * @param int $amount The amount of rows
     * @return $this The QueryBuilder
     */
    public function limit($amount)
    {
        $this->limit = intval($amount);
        return $this;
    }

    /**
     * Escapes all backticks in the given string
     *
     * @param string $string The string to escape
     * @return string The espcaped string
     */
    protected static function escapeBacktick($string)
    {
        return str_replace('`', '``', $string);
    }

    /**
     * Performs the query
     * @return PDOStatement The PDO statement
     */
    public function perform()
    {
        return self::$pdo->query((string) $this);
    }

    /**
     * Gets the maximum amount of results query can return
     *
     * @return int The limit
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Gets the amount of rows skipped
     *
     * @return int The amount
     */
    public function getSkip()
    {
        return $this->skip;
    }

    /**
     * Gets all the WHERE conditions this query has
     *
     * @return WhereCondition[] The conditions
     */
    public function getWhereConditions()
    {
        return $this->wheres;
    }

    /**
     * Gets all the ORDER BY rules this query has
     *
     * @return OrderRule[] The rules
     */
    public function getOrderRules()
    {
        return $this->orders;
    }

    /**
     * Convert $field in a string representing a field,
     * either in `field` or `table.field` notation
     *
     * @param array|string $field An array [table, field] or
     *              a string wiht the field name
     * @throws Exception If the given field is an empty array
     * @return string The (table.)field name surrounded by backticks (`)
     */
    public static function fieldName($field)
    {
        // If it's an array
        if (is_array($field)) {
            // And it has a table and field
            if (isset($field[1]))
                // Return in the `table.field` notation
                return '`'. str_replace('`', '``', $field[0]) . '`.`'. str_replace('`', '``', $field[1]) .'`';

            // If it just has a field
            elseif (isset ($field[0]))
                // Convert it into a string
                $field = $field[0];

            // If it's an empty array
            else
                // ERROR
                throw new Exception('The given field name is an empty array.');
        }

        // Return in the normal `field` notation
        return '`'. str_replace('`', '``', $field) . '`';
    }

    /**
     * Converts (a) table name(s) (string or array or multidimensional array)
     * into a string in the MySQL notation
     *
     * @param string|array|array[] $table The table(s)
     * @throws Exception When the given table name is an empty array.
     * @return string The MySQL notation
     */
    public static function tableName($table)
    {
        // If it's an array
        if (is_array($table))
        {
            $count = count($table);
            // If the array is empty
            if (!$count) {
                throw new Exception('The given table name is an empty array.');
            }
            // If the array is multidimensional
            elseif (self::arrayIsMultidimensional($table))
            {
                return '`'. str_replace('`', '``', $table) .'`';
            }
            // If the array contains a database and table
            elseif($count > 1) {
                return '`'.str_replace('`', '``', first($table)).'`.`'. str_replace('`', '``', next($table)) .'`';
            }
            // If the array contains just a table
            else {
                return '`'. str_replace('`', '``', first($table)) .'`';
            }
        }
        // If it's just a string or something
        else {
            return '`'. str_replace('`', '``', $table) .'`';
        }
    }

    /**
     * Checks whether an array is multidimensional
     *
     * @param array $array The array to check
     * @return boolean Whether the array is multidimensional
     */
    protected static function arrayIsMultidimensional(array $array)
    {
        foreach($array as $a)
            if (is_array($a)) return true;
        return false;
    }

    /**
     * Generates a string containg all the where conditions
     * @param WhereCondition[] &$conditions A collection of conditions
     * @return string All the where-conditions linked
     */
    protected static function generateWhere(&$conditions)
    {
        $query = '';

        // Check whether or not to use WHERE
        $whereCount = count($conditions);
        if ($whereCount) {
            $query .= ' WHERE';

            // Loop through the WHERE rules
            for ($i = 0; $i < $whereCount; $i++) {
                $where = $conditions[$i];

                // Add OR / AND when needed
                if ($i)
                    $query .= $where->or ? ' OR' : ' AND';

                $query .= ' '. self::fieldName($where->field) .' ';

                // Select the right where-type and add the condition
                // to the query string
                switch ($where->type) {
                    case self::EQ:
                        if ($where->value === null)
                            $query .= 'IS NULL';
                        else
                            $query .= '= '. self::$pdo->quote($where->value);
                        break;
                    case self::NEQ:
                        if ($where->value === null)
                            $query .= 'IS NOT NULL';
                        else
                            $query .= '!= '. self::$pdo->quote($where->value);
                            break;
                    case self::IN:
                        // Make sure the value is an array
                        if (!is_array($where->value))
                            throw new UnexpectedValueException('The given value for the WHERE IN is not an array.');

                        // Make sure it's not empty
                        if (!count($where->value))
                            throw new UnexpectedValueException('The given array for the WHERE IN is empty.');

                        // Quote all the values
                        $quoted = array();
                        foreach ($where->value as $value)
                            $quoted[] = self::$pdo->quote($value);

                        $query .= 'IN ('. implode(', ', $quoted) .')';
                        unset($quoted);
                        break;
                    case self::GT:
                        $query .= '> '. self::$pdo->quote($where->value);
                        break;
                    case self::LT:
                        $query .= '< '. self::$pdo->quote($where->value);
                        break;
                    case self::GTEQ:
                        $query .= '>= '. self::$pdo->quote($where->value);
                        break;
                    case self::LTEQ:
                        $query .= '<= '. self::$pdo->quote($where->value);
                        break;
                    case self::REFS:
                        $query .= ' = '. self::$pdo->quote($where->value->__toString());
                        break;
                    default:
                        throw new DomainException('The given WHERE type #'. strip_tags($where->type) .' is not implemented (yet).');
                        break;
                }
            }
        }
        return $query;
    }

    /**
     * Generates a string containing all the order rules
     * @param
     */
    protected static function generateOrder(&$orders)
    {
        $orderCount = count($orders);
        if (!$orderCount)
            return '';

        $query = ' ORDER BY';
        for ($i = 0; $i < $orderCount; $i++) {
            // Add , if not the first order rule
            if ($i)
                $query .= ', ';

            $rule = $orders[$i];
            $query .= ' '. self::fieldName($rule->field) .($rule->descending ? ' DESC' : ' ASC');
        }

        return $query;
    }
}

/**
 * Internal one-dimensional datastructure representing a where-condition
 */
class WhereCondition
{
    /**
     * @var boolean $or Whether this condition must be tested only if the
     *              previous condition failed.
     * @var string $field The field name
     * @var int $type The condition type
     * @var mixed $value The value the condition must test for
     */
    public $or, $field, $type, $value;

    public function __construct($or, $field, $type, $value)
    {
        if (!is_int($type) || $type < 0 || $type > 7)
            throw new UnexpectedValueException('The type for this condition is not valid.');

        $this->or = $or == true;
        $this->field = $field;
        $this->type = $type;
        $this->value = $value;
    }
}

/**
 * Internal one-dimensional datastructure representing an order rule
 */
class OrderRule
{
    /**
     * @var string|array $field The name of the field the rule applies to
     * @var boolean $descending Whether to order descending
     */
    public $field, $descending;

    /**
     * Initiates a new Order rule
     *
     * @param string|array $field The field name
     * @param boolean $descending Whether to order descending
     */
    public function __construct ($field, $descending = false) {
        $this->field = $field;
        $this->descending = $descending == true;
    }
}
