<?php

namespace Xesau\SqlXS;

class XsInfo
{
    /**
     * @var string $table The name of the table in the database.
     * @var string $uniqueField The name of a unique/primary field name to identify rows
     * @var string[] $readable An array of readable fields, for automatic getters.
     * @var string[] $writable An array of writable fields, for automatic setters.
     * @var array $types An associative array [field name] => "Class Name"
     *          for every field that represents an object. The class must use SqlXS
     *          It is used in automatically generated getters and setters.
     */
    private $table, $uniqueField;
    private $readable, $writable;
    private $types;

    /**
     * Creates a new Table Configuration
     *
     * @param string $table The name of the table in the database.
     * @param string $uniqueField The name of a unique/primary field name to identify rows.
     * @param string[] $readable An array of readable fields, for automatic getters.
     * @param string[] $writable An array of writable fields, for automatic setters.
     * @param array $types An associative array [field name] => "Class Name"
     *          for every field that represents an object. The class must use SqlXS
     *          It is used in automatically generated getters and setters.
     */
    public function __construct($table, $uniqueField, array $readable = null, array $writable = null, $types = array()) {
        $this->table = $table;
        $this->uniqueField = $uniqueField;
        $this->readable = $readable;
        $this->writable = $writable;
        $this->types = $types;
    }

    /**
     * Gets the name of the table in the database.
     *
     * @return string The table's name.
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Gets the name of the unique field.
     *
     * @return string The unique field's name.
     */
    public function getUniqueField()
    {
        return $this->uniqueField;
    }

    /**
     * Checks whether the given field is readable or not.
     *
     * @param string $field The name of the field.
     * @return boolean True when it's readable, false when it's not.
     */
    public function isReadable($field)
    {
        return $this->readable == null || $field == $this->uniqueField || in_array($field, $this->readable);
    }

    /**
     * Checks whether the given field is writable or not.
     *
     * @param string $field The name of the field.
     * @return boolean True when it's writable, false when it's not.
     *                  If the field is the unique field, it can't be writable either.
     */
    public function isWritable($field)
    {
        return $this->writable == null || $field != $this->uniqueField && in_array($field, $this->writable);
    }

    /**
     * Gets the type for this
     */
    public function getType($field)
    {
        return isset($this->types[$field]) ? $this->types[$field] : null;
    }

}
