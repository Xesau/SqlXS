<?php

/**
 * SqlXS - A quick and easy solution for all your complex SQL queries
 *
 * @author Xesau <info@xesau.eu> https://xesau.eu/project/sqlxs
 * @version 1.0
 * @package SqlXS
 */

namespace Xesau\SqlXS;

use PDO, PDOStatement;
use PDOException, Exception;
use DomainException, UnexpectedValueException;

/**
 * SqlXS trait
 */
trait XS
{
    /**
     * @var XS[] $buffer The buffer of object instances
     * @var int $id The identifier of the instance
     * @var array $data The data this object contains
     * @var array $changes The changes made to the object
     */
    private static $buffer = array();
    private static $fieldTable = false;
    private $id, $data, $unloaded = array(), $changes = array();

    /**
     * Returns SqlXS table configuration, containing the table name, the
     * unique field's name, and the writable and readable fields.
     *
     * @return XsInfo The table information
     */
    public abstract function sqlXS();

    /**
     * Initiates a new instance of this object
     *
     * @param mixed $id The unique identifier of the row
     * @throws XsException When there is no such row or when there is a PDO error
     */
    private function __construct($id)
    {
        $xs = self::sqlXS();

        try {
            $stmt = XsConfiguration::getPDO()->prepare('SELECT * FROM '. QueryBuilder::tableName($xs->getTable()) .
                    ' WHERE '. QueryBuilder::fieldName($xs->getUniqueField()) .' = ? LIMIT 1');
            $stmt->execute(array($id));

            if ($stmt->rowCount() == 0) {
                throw new XsException('There is no row with '. strip_tags($xs->getUniqueField()) .' = '. strip_tags($id));
            }

            $this->id = $id;
            $this->data = $stmt->fetch(PDO::FETCH_ASSOC);

            foreach ($this->data as $field => $value) {
                $type = $xs->getType($field);
                if ($type !== null) {
                    $this->unloaded[] = $field;
                }
            }
        } catch (PDOException $ex) {
            throw new XsException('PDO Error occurred: ' . $ex->getMessage());
        }
    }

    /**
     * Magic method that handles the get....() and set....($value) methods
     *
     * @param string $functionName The name of the function.
     * @param mixed[] $arguments The function arguments passed on.
     * @throws DomainException When the given field doesn't exist.
     * @throws XsException When the $value argument is missing.
     * @return $this|mixed|null If no valid function was passed, returns null.
     *             If get....() is used, the value of that field is returned.
     *             If set....($value) is used, it returns the object.
     */
    public function __call($functionName, $arguments)
    {
        // Make sure the function name contains get* or set* at least
        if (strlen ($functionName) < 4) return;
        $functionName = strtolower($functionName);
        $action = substr ($functionName, 0, 3);
        if ($action != 'get' && $action != 'set')
            trigger_error('Call to undefined method '.__CLASS__.'::'.$functionName.'()', E_USER_ERROR);

        // Get the field
        $field = substr ($functionName, 3);

        // If the action is get and the field is readable, pass it onto the getField method
        if ($action == 'get') {
            if (!self::sqlXS()->isReadable($field) && !self::sqlXS()->isReadable($this->getPossibleFieldname($field))) {
                    throw new DomainException('The given field '. strip_tags(self::sqlXS()->getTable()) .'.'. strip_tags($field) .' is not readable.');
            } else {
                return $this->getField($field);
            }
        // If the action is set and the field is writable, pass it onto the setField method
        } elseif (isset($arguments[0])) {
            if (!self::sqlXS()->isWritable($field) && !self::sqlXS()->isWritable($this->getPossibleFieldname($field))) {
                    throw new DomainException('The given field '. strip_tags(self::sqlXS()->getTable()) .'.'. strip_tags($field) .' is not writable.');
            } else {
                return $this->setField($field, $arguments[0]);
            }
        } else {
            throw new XsException(__CLASS__ .'::'. $functionName .' needs a $value argument.');
        }
    }

    /**
     * Gets a field's value
     *
     * @param string $field The field's name
     * @throws DomainException When the field doesnt exist
     * @return mixed The value of the field
     */
    private function getField($field)
    {
        if (!array_key_exists($field, $this->data)) {
            if(!array_key_exists($tmp = $this->getPossibleFieldname($field), $this->data)) {
                throw new DomainException('The given field '. strip_tags(self::sqlXS()->getTable()) .'.'. strip_tags($field) . ' is not defined.');
            } else {
                $field = $tmp;
            }
        }

        // Eager loading of recerening objects
        if (in_array($field, $this->unloaded)) {
            $type = self::sqlXS()->getType($field);
            $this->data[$field] = $type::byID($this->data[$field]);
            unset($this->unloaded[array_search($field, $this->unloaded)]);
        }

        return $this->data[$field];
    }


    /**
     * Sets a field to a new value
     *
     * @param string $field The field name
     * @param mixed|SqlXS $value The value. If there is a type given for the field, the
     *                      field value is set to the identifier of the object
     * @return $this Builder-style object
     */
    private function setField($field, $value)
    {
        if (!array_key_exists($field, $this->data)) {
            if(!array_key_exists($tmp = $this->getPossibleFieldname($field), $this->data)) {
                throw new DomainException('The given field '. strip_tags(self::sqlXS()->getTable()) .'.'. strip_tags($field) . ' is not defined.');
            } else {
                $field = $tmp;
            }
        }

        $type = self::sqlXS()->getType($field);
        if ($type !== null) {
            if (is_scalar($value)) {
                $value = $type::byID($value);
            } elseif ($type !== get_class($value)) {
                throw new UnexpectedValueException('The value for '. strip_tags(self::sqlXS()->getTable()) .'.'. strip_tags($field) . ' does not match the given type ('. strip_tags(basename($type)) .'.');
            }
        }

        $this->data[$field] = $value;
        $this->changes[$field] = ($type == null ? $value : $value->id());
        return $this;
    }

    /**
     * Gets a possible field name based on the given $prediction
     */
    private function getPossibleFieldname($prediction)
    {
        if (self::$fieldTable !== false)
            if (isset(self::$fieldTable[$prediction])) {
                if (isset($this->data[self::$fieldTable[$prediction]]))
                    return self::$fieldTable[$prediction];
            } elseif (($alias = self::getPossibleFieldAlias($prediction)) != null)
                return self::$fieldTable[$alias];

        foreach(array_keys($this->data) as $possibleField)
        {
            if (str_replace('_', '', $possibleField) == $prediction)
                return $possibleField;
        }
        return null;
    }

    private static function getPossibleFieldAlias($prediction)
    {
        if (self::$fieldTable === false)
            return null;

        foreach(self::$fieldTable as $alias => $field) {
            if (($i = str_replace('_', '', $alias)) == $prediction)
                return $alias;
        }
        return $null;
    }

    public static function registerFieldTable(array $table)
    {
        self::$fieldTable = $table;
    }

    /**
     * Gets an object by it's identifier
     *
     * @param mixed $id The value of the unique field
     * @return XS|null The object, or NULL if there was no object found.
     */
    public static function byID($id)
    {
        if (array_key_exists($id, self::$buffer)) {
            return self::$buffer[$id];
        } else {
            try {
                self::$buffer[$id] = new static($id);
                return self::$buffer[$id];
            } catch (XsException $ex) {
                return self::$buffer[$id] = null;
            }
        }
    }

    /**
     * Releases bufferd object if it exists
     *
     * @param mixed $id The identifier of the object
     * @return boolean True if there was one
     */
    public static function release($id)
    {
        if (isset($this->buffer[$id])) {
            unset($this->buffer[$id]);
            return true;
        } else return false;
    }

    /**
     * Releases all bufferd objects
     */
    public static function releaseAll()
    {
        $this->buffer = array();
    }

    /**
     * Returns the ID of this object
     *
     * @return mixed The ID
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Returns all the data
     *
     * @param int $nest Nest data
     * @return array The data
     */
    public function data($nest = 1)
    {
        $xs = $this->sqlXS();

        $result = $this->data;

        // Loop over each field
        foreach($result as $key => $value) {

            // if it's not readable, remove it from the result
            if (!$xs->isReadable($key))
                unset($result[$key]);
            elseif($nest > 0) {
                if (in_array($key, $this->unloaded)) {
                    $nxs = $xs->getType($key);
                    $this->data[$key] = $nxs::byID($value);
                }

                if(is_object($this->data[$key]))
                    $result[$key] = $this->data[$key]->data($nest - 1);
            }
        }
        return $result;
    }

    public function __toString()
    {
        return $this->id();
    }

    /**
     * Creates a new Select Query Builder for this table
     *
     * @return XsQueryBuilder The query builder
     */
    public static function select()
    {
        return new XsQueryBuilder(QueryBuilder::SELECT, __CLASS__);
    }

    /**
     * Creates a new Update Query Builder for this table
     *
     * @param array $fields The fields for the update query
     * @return QueryBuilder The Query Builder
     */
    public static function update(array $fields)
    {
        if (!count($fields))
            throw new XsException('Cannot update a row when no fields are provided.');

        return new QueryBuilder(QueryBuilder::UPDATE, self::sqlXS()->getTable(), $fields);
    }

    /**
     * Creates a new Delete Query Builder for this table
     *
     * @return QueryBuilder The Query Builder
     */
    public static function delete()
    {
        return new QueryBuilder(QueryBuilder::DELETE, self::sqlXS()->getTable());
    }

    /**
     * Creates a new Count Query Builder for this table
     *
     * @return XsQueryBuilder The Query Builder
     */
    public static function count()
    {
        return new XsQueryBuilder(QueryBuilder::COUNT, __CLASS__);
    }

    /**
     * Inserts a new row in the table
     *
     * @param array $data The [fields] => values
     * @throws XsException
     */
    public static function insert(array $data)
    {
        foreach ($data as $f => $v) {
            $fields[] = QueryBuilder::fieldName($f);

            // Make sure type is correct
            $type = self::sqlXS()->getType($f);
            if ($type !== null && !is_scalar($v))
                $v = $v->id();

            $values[] = XsConfiguration::getPDO()->quote($v);
        }

        try {
            XsConfiguration::getPDO()->query('INSERT INTO '. QueryBuilder::tableName(self::sqlXS()->getTable()) .' ('. implode(', ', $fields) .') VALUES ('. implode(', ', $values) .')');
            return self::byID(XsConfiguration::getPDO()->lastInsertId());
        } catch (PDOException $ex) {
            throw new XsException('Could not insert row. PDO Error: '. $ex->getMessage());
        }
    }


    /**
     * Saves any changes to the database
     *
     * @return $this The Object
     */
    public function save()
    {
        if (!count($this->changes))
            return $this;

        self::update($this->changes)
        ->where(self::sqlXS()->getUniqueField(), QueryBuilder::EQ, $this->id())
        ->perform();

        $this->changes = array();
    }

    /**
     * Destruction controller that saves changes to the datbase
     */
    public function __destruct()
    {
        $this->save();
    }

}
