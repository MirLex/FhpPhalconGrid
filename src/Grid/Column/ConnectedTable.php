<?php
/**
 * ConnectedTable
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Column;

use FhpPhalconGrid\Grid\Grid;

class ConnectedTable
{

    private $alias;
    private $sourceTable;
    private $sourceField;
    private $connectedTable;
    private $connectedField;
    private $connectedFromField;
    private $connectedToField;
    private $displayFields = array(Grid::TABLE => null, Grid::DETAILS => null, Grid::EDIT => null,Grid::NEW=>null);
    private $where;

    /**
     * @return mixed
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @param mixed $where
     */
    public function setWhere($where)
    {
        $this->where = $where;
        return $this;
    }

    public function getFieldValues()
    {
        $arr = array();

        $table = $this->getSourceTable();
        $res = $table::find($this->getWhere());

        foreach ($res as $row) {
            $row = $row->toArray();
            if ($this->getDisplayFields() == null) {
                $arr[$row[$this->getSourceField()]] = $row[$this->getSourceField()];
            } else {
                $nameFields = $this->getDisplayFields();
                if (!is_array($nameFields)) {
                    $nameFields = array($nameFields);
                }
                $name = '';
                foreach ($nameFields as $nameField) {
                    if (isset($row[$nameField])) {
                        $name .= $row[$nameField];
                    } else {
                        $name .= $nameField;
                    }
                }
                $arr[$row[$this->getSourceField()]] = $name;
            }
        }
        return $arr;
    }

    /**
     * @return mixed
     */
    public function getConnectedFromField()
    {
        return $this->connectedFromField;
    }

    /**
     * @param mixed $connectedFromField
     */
    public function setConnectedFromField($connectedFromField)
    {
        $this->connectedFromField = $connectedFromField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param mixed $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceTable()
    {
        return $this->sourceTable;
    }

    /**
     * @param mixed $sourceTable
     */
    public function setSourceTable($sourceTable)
    {
        $this->sourceTable = $sourceTable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceField()
    {
        return $this->sourceField;
    }

    /**
     * @param mixed $sourceField
     */
    public function setSourceField($sourceField)
    {
        $this->sourceField = $sourceField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConnectedTable()
    {
        return $this->connectedTable;
    }

    /**
     * @param mixed $connectedTable
     */
    public function setConnectedTable($connectedTable)
    {
        $this->connectedTable = $connectedTable;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConnectedField()
    {
        return $this->connectedField;
    }

    /**
     * @param mixed $connectedField
     */
    public function setConnectedField($connectedField)
    {
        $this->connectedField = $connectedField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConnectedToField()
    {
        return $this->connectedToField;
    }

    /**
     * @param mixed $connectedSourceField
     */
    public function setConnectedToField($connectedToField)
    {
        $this->connectedToField = $connectedToField;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDisplayFields($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }

        return $this->displayFields[$mode];
    }

    /**
     * @param mixed $displayFields
     * @return $this
     */
    public function setDisplayFields($displayFields, $mode = null)
    {
        $this->displayFields = $this->_setModeOptions($this->displayFields, $displayFields, $mode);
        return $this;
    }


    /**
     * TODO its the same method than in AbstractColumn
     * @param $field
     * @param $value
     * @param $mode
     * @return mixed
     * @throws Exception
     */
    protected function _setModeOptions($field, $value, $mode)
    {
        $types = array(Grid::TABLE, Grid::DETAILS, Grid::EDIT, Grid::NEW);
        if ($mode === null) {
            foreach ($types as $mode) {
                $field[$mode] = $value;
            }
        } else {
            if (!in_array($mode, $types)) {
                throw new Exception('This mode "' . $mode . '" is not allowed!');
            }
            $field[$mode] = $value;
        }
        return $field;
    }

}