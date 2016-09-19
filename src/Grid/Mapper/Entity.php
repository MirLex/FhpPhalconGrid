<?php
/**
 * Entity
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Mapper;

class Entity
{
    /**
     * Name of the entity
     * @var String $name
     */
    private $name;

    /**
     * Name of the database schema
     * @var String $schema
     */
    private $schema;

    /**
     * Name of the database table
     * @var String $table
     */
    private $table;

    /**
     * aliases of the database table
     * @var array $aliases
     */
    private $aliases;

    /**
     * primary keys of that table
     * @var array $primary
     */
    private $primary;

    /**
     * related keys of that table
     * @var array $relatedFields
     */
    private $relatedFields;

    private $relations;

    /**
     * @return mixed
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * @param mixed $relations
     */
    public function setRelations($relations)
    {
        $this->relations = $relations;
        return $this;
    }

    /**
     * All columns of that table
     * @var array $columns
     */
    private $columns = array();

    /**
     * @return String
     */
    public function setRelatedFields($relatedFields)
    {
        $this->relatedFields = $relatedFields;
        return $this;
    }

    /**
     * @return String
     */
    public function getRelatedFields()
    {
        return $this->relatedFields;
    }

    /**
     * @return String
     */
    public function setPrimary($primary)
    {
        $this->primary =$primary;
        return $this;
    }

    /**
     * @return String
     */
    public function getPrimary()
    {
        return $this->primary;
    }

    /**
     * @return String
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return String
     */
    public function getPhqlName()
    {
        return '[' . $this->name . ']';
    }

    /**
     * @param String $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return String
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param String $schema
     * @return $this
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * @return String
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param String $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param String $column
     * @return array
     * @throws Exception
     */
    public function getColumn($column)
    {
        if (!isset($this->columns[$column])) {
            throw new Exception('The column "' . $column . '" does not exist!');
        }
        return $this->columns[$column];
    }

    /**
     * @param array $columns
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Returns the table name with the schema prefix
     * @param String $schema
     * @param String $table
     * @return string
     */
    static public function getTableNameWithSchema($schema, $table)
    {
        return $schema . '.' . $table;
    }

    /**
     * @return array
     */
    public function getAlias()
    {
        return $this->aliases;
    }

    /**
     * @param String $alias
     * @return $this
     */
    public function addAlias($alias)
    {
        $this->aliases[] = $alias;
        return $this;
    }

}