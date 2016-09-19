<?php
/**
 * Columns
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid;

use FhpPhalconGrid\Grid\Column\AbstractColumn;
use FhpPhalconGrid\Grid\Column\Exception;
use FhpPhalconGrid\Grid\Column\Permission;
use FhpPhalconGrid\Grid\Column\Security;

class Column extends AbstractColumn
{
    const SPLIT = '__';
    const TYPE_QUERY = 'query';
    const TYPE_SUBQUERY = 'subquery';
    const TYPE_FUNCTION = 'function';
    const TYPE_EXTRACOLUMN = 'extra';

    /**
     * Name of the model
     * @var String
     */
    private $modelName;

    /**
     * Type of the column
     * @var String
     */
    private $type = self::TYPE_QUERY;

    /**
     * Table name of the column
     * @var null|String
     */
    private $table = null;

    /**
     * Schema name of the table
     * @var null|String
     */
    private $schema = null;

    /**
     * Alias name of the table if exist
     * @var null|String
     */
    private $tableAlias = null;

    /**
     * Alias name of the field
     * @var null|String
     */
    private $alias = null;

    /**
     * TODO - needed????
     * Value of the column
     * @var mixed
     */
    private $value;

    /**
     * custom edit field can be set
     * @var null|\Phalcon\Forms\Element
     */
    private $validator = null;
    private $fieldType = null;
    private $fieldValue = null;


    /**
     * helper to check if build() was already called
     * @var bool
     */
    private $build = false;


    /**
     * @return String
     */
    public function setFieldValue($fieldValue)
    {
        if(!is_array($fieldValue)){
            $fieldValue = explode(',',str_replace('\'','',$fieldValue));
            $fieldValue = array_combine($fieldValue, $fieldValue);
        }
        $this->fieldValue = $fieldValue;
        return $this;
    }

    /**
     * @return null|Array
     */
    public function getFieldValue()
    {
        return $this->fieldValue;
    }

    /**
     * @return String
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @return Permission
     */
    public function getPermission()
    {
        return $this->permission->getRules('onlyEdit');
    }

    /**
     * @param String $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return String
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }


    /**
     * @return String
     * @throws Exception
     */
    public function getFieldTypeAjax()
    {
        if (empty($this->fieldType)) {
            return false;
        }

        switch ($this->fieldType) {

            case "int":
            case "tinyint":
            case "smallint":
            case "mediumint":
            case "bigint":
                $type = "int";
                break;
            case "float":
            case "double":
            case "decimal":
                $type = "float";
                break;
            case "datetime":
            case "timestamp":
                $type = "datetimepicker";
                break;
            case "date":
                $type = "datepicker";
                break;
            case "time":
                $type = "timepicker";
                break;
            case "year":
                $type = "year";
                break;
            case "char":
            case "varchar":
                $type = "text";
                break;
            case "text":
            case "tinytext":
            case "mediumtext":
            case "longtext":
                $type = "textarea";
                break;
            case "enum":
                $type = "select";
                break;
            case "set":
                $type = "multiselect";
                break;
            default:
                throw new Exception('The db field type "' . $this->fieldType . '" is unknown!');
                break;

        }

        return $type;
    }


    /**
     * @param String $dbType
     * @return $this
     */
    public function setFieldType($fieldType)
    {
        $this->fieldType = $fieldType;
        return $this;
    }

    /**
     * @return null|String
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
     * @return null|String
     */
    public function getTableAlias()
    {
        return $this->tableAlias;
    }

    /**
     * @param String $tableAlias
     * @return $this
     */
    public function setTableAlias($tableAlias)
    {
        $this->tableAlias = $tableAlias;
        return $this;
    }

    /**
     * @return null|String
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param null $alias
     * @return $this
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @param $valitator
     */
    public function setValidator($valitator)
    {
        $this->validator = $valitator;
    }

    public function getValidators()
    {
        return $this->validator;
    }

    public function getValidatorsAjax()
    {

        $validators = array();
        if (is_array($this->validator) AND count($this->validator) > 0) {
            foreach ($this->validator as $name => $validator) {
                if ($validator === null) {
                    continue;
                }
                $arr = explode('\\', get_class($validator));
                $validators[array_pop($arr)] = $validator->getOptions();
            }
        }
        return $validators;
    }

    /**
     * @param $default
     * @return $this
     */
    public function setDefault($default)
    {
        $this->value = $default;
        return $this;
    }

    /**
     * @return null|String
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param mixed $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }


    /**
     * @return null|String
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }


    /**
     * @param boolean $short
     * @return mixed
     */
    public function getModelName($short = false)
    {
        if ($short == true) {
            return substr($this->modelName, strrpos($this->modelName, '\\') + 1);
        }
        return $this->modelName;
    }

    /**
     * @param mixed $modelName
     * @return $this
     */
    public function setModelName($modelName)
    {
        $this->modelName = $modelName;
        return $this;
    }


    /**
     * modify the object
     * @return $this
     */
    public function build()
    {
        if ($this->build === false) {
            if ($this->getField() == null) {
                $this->setField($this->getName(Grid::TABLE));
            } else {
                $this->setTableAlias(null);
            }

            if ($this->getTableAlias() == $this->getTable()) {
                $this->setTableAlias($this->getModelName());
            }
            $this->build = true;
        }
        return $this;
    }

    /**
     * @return String
     */
    public function getAliasOrField()
    {
        return ($this->getAlias() ? $this->getAlias() : $this->getField());
    }

    public function getPhqlFieldName()
    {
        return '[' . ($this->getTableAlias() != null ? $this->getTableAlias() : $this->getModelName()) . '].' . $this->getAliasOrField();
    }
}