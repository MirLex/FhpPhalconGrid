<?php
/**
 * Group
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Column;

class Group extends AbstractColumn
{
    const GROUP_PREFIX = 'group__';
    const SPLIT = '__';

    private $columnSeparator = ',';
    private $lineSeparator = '</br>';
    private $group = true;

    private $columns = array();
    private $lastPosition = 0;

    public function getAliasOrField()
    {
        return self::GROUP_PREFIX . $this->getName();
    }

    public function isGroup()
    {
        return $this->group;
    }

    public function getColumns(){
        return $this->columns;
    }

    /**
     * @param String $column
     * @return mixed
     * @throws Exception
     */
    public function getColumn($column)
    {
        if (!isset($this->columns)) {
            throw new Exception('The Column "' . $column . '" does not exist!');
        }
        return $this->columns[$column];
    }

    /**
     * Get all columns with a valid phql "[table].column" as key
     * @return array
     */
    public function getColumnsForSelect()
    {
        uasort($this->columns, 'FhpPhalconGrid\Grid\Grid::_sortingColumns');

        $columns = array();
        /** @var \FhpPhalconGrid\Grid\Column $column */
        foreach ($this->columns as $column) {
            if (!$column->isSqlRemove()) {
                $columns[$column->getPhqlFieldName()] = $column;
            }
        }
        return $columns;
    }

    /**
     * Adds a column to the group
     * @param \FhpPhalconGrid\Grid\Column $column
     * @return $this
     */
    public function addColumn($column)
    {

        $name = Group::getColumnName($column->getAlias());
        $column->setName($name)
            ->setField($name)
            ->setAlias($name)
            ->setPosition($this->lastPosition++)
            ->build();

        $this->columns[$name] = $column;
        return $this;
    }


    /**
     * Helper to creates a string with the format PREFIX.relation name.SPLIT.column name
     * @param $relationAlias
     * @param $column
     * @return string
     */
    static public function getAliasName($relationAlias, $column)
    {
        return self::GROUP_PREFIX . $relationAlias . self::SPLIT . $column;
    }

    /**
     * Helper to get the alias name out of the alias mapper
     * @param $aliasName
     * @param bool $withoutPrefix
     * @return string
     */
    static public function getRelationAlias($aliasName, $withoutPrefix = false)
    {
        if ($withoutPrefix !== false) {
            return substr($aliasName, strlen(self::GROUP_PREFIX), strrpos($aliasName, self::SPLIT) - strlen(self::GROUP_PREFIX));
        }
        return substr($aliasName, 0, strrpos($aliasName, self::SPLIT));
    }

    /**
     * Helper to get the column name out of the alias mapper
     * @param $aliasName
     * @return string
     */
    static public function getColumnName($aliasName)
    {
        return substr($aliasName, strrpos($aliasName, self::SPLIT) + strlen(self::SPLIT));
    }

    /**
     * @return string
     */
    public function getLineSeparator()
    {
        return $this->lineSeparator;
    }

    /**
     * @param string $lineSeparator
     * @return $this
     */
    public function setLineSeparator($lineSeparator)
    {
        $this->lineSeparator = $lineSeparator;
        return $this;
    }

    /**
     * @return string
     */
    public function getColumnSeparator()
    {
        return $this->columnSeparator;
    }

    /**
     * @param string $columnSeparator
     * @return $this
     */
    public function setColumnSeparator($columnSeparator)
    {
        $this->columnSeparator = $columnSeparator;
        return $this;
    }
}