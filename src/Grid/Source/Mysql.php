<?php
/**
 * Grid
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Source;

use FhpPhalconGrid\Grid\Validator\ArrayValidator;
use FhpPhalconGrid\Grid\Validator\BetweenValidator;
use FhpPhalconGrid\Grid\Validator\DateValidator;
use FhpPhalconGrid\Grid\Validator\FloatValidator;
use FhpPhalconGrid\Grid\Validator\RequiredValidator;
use FhpPhalconGrid\Grid\Validator\StringLengthValidator;
use Phalcon\DiInterface;
use Phalcon\Mvc\User\Plugin;
use Phalcon\Validation;

class Mysql extends Plugin implements SourceInterface
{

    /** @var array $columns */
    private $columns = array();


    private function extractStringBetween($cFirstChar, $cSecondChar, $sString)
    {
        preg_match_all("/\\" . $cFirstChar . "(.*?)\\" . $cSecondChar . "/", $sString, $aMatches);
        return $aMatches[1];
    }

    /**
     * Describe the given table and save all columns to an array
     *
     * @param String $table
     * @param \Phalcon\DiInterface $di
     * @throws Exception
     */
    public function __construct($table, DiInterface $di)
    {
        $this->setDI($di);

        if (!$this->getDI()->has('cache')) {
            throw new Exception('Please initialize the cache service!');
        }

        $cache = $this->getDI()->get('cache');
        if ($cache->get('DESCRIBE_' . $table) === null) {
            /** @var \Phalcon\Db\Result\Pdo $query */
            $query = $this->getDI()->get('db')->query('DESCRIBE ' . $table);

            foreach ($query->fetchAll() as $column) {

                if (strpos($column['Type'], '(')) {
                    $this->columns[$column['Field']]['type'] = substr($column['Type'], 0, strpos($column['Type'], '('));
                    $length = $this->extractStringBetween('(', ')', $column['Type']);
                    if (isset($length[0])) {
                        if (in_array($this->columns[$column['Field']]['type'], array('enum', 'set'))) {
                            $length[0] = str_replace('\'', '', $length[0]);
                        }
                        $this->columns[$column['Field']]['length'] = $length[0];
                    }
                } else {
                    $this->columns[$column['Field']]['type'] = $column['Type'];
                }

                $schema = null;
                if (strpos($table, '.')) {
                    $schema = substr($table, 0, strpos($table, '.'));
                    $table = substr($table, strpos($table, '.') + 1);
                }

                $this->columns[$column['Field']]['schema'] = $schema;
                $this->columns[$column['Field']]['table'] = $table;
                $this->columns[$column['Field']]['unsigned'] = (strpos($column['Type'], 'unsigned') !== false) ? true : false;
                $this->columns[$column['Field']]['default'] = $column['Default'];
                $this->columns[$column['Field']]['nullable'] = (strtoupper($column['Null']) == "YES") ? true : false;
                $this->columns[$column['Field']]['key'] = (strpos($column['Key'], 'PRI') !== false OR strpos($column['Key'], 'UNI') !== false) ? true : false;
            }

            $cache->save('DESCRIBE_' . $table, serialize($this->columns));
        } else {
            $this->columns = unserialize($cache->get('DESCRIBE_' . $table));
        }
    }

    /**
     * Get all described columns of the table
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }


    /**
     * Get the form element for the edit view
     * @param String $type
     * @param array $options
     * @return Validation\Validator[]
     */
    static public function getValidator($type, $options)
    {

        if ($type === null) {
            return false;
        }

        self::_checkType($type);

        $validators = array();

        //if field is required
        if ($options['nullable'] === false) {
            $validators[] = new RequiredValidator(array(
                'message' => 'The field is required'
            ));
        }

        //ints
        if (in_array($type, array('int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint'))) {
            $validators[] = self::_int($type, $options);
        }

        //floats
        if (in_array($type, array('float', 'decimal', 'double'))) {
            $validators[] = new FloatValidator(array(
                'message' => 'The number is invalid!',
                'allowEmpty' => true
            ));
        }
        array('float', 'decimal', 'double');

        //strings
        if (in_array($type, array('char',
            'varchar',
            'text',
            'tinytext',
            'mediumtext',
            'longtext'))) {

            $validators[] = self::_strings($type, $options);
        }

        //dates
        if (in_array($type, array('date', 'datetime', 'timestamp', 'time', 'year'))) {
            $validators[] = self::_date($type);
        }

        //arrays
        if (in_array($type, array('enum', 'set'))) {
            $validators[] = new ArrayValidator(array(
                'type' => $type,
                'options' => $options,
                'message' => 'The value is not allowed!',
                'allowEmpty' => true
            ));
        }

        return $validators;
    }

    /**
     * Checks the allowed mysql types
     * @param $type
     * @throws Exception
     */
    private static function _checkType($type)
    {
        $types = array(
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'float',
            'double',
            'decimal',

            'date',
            'datetime',
            'timestamp',
            'time',
            'year',

            'char',
            'varchar',
            'text',
            'tinytext',
            'mediumtext',
            'longtext',
            'enum',
            'set'
        );

        if (!in_array($type, $types)) {
            throw new Exception('MySQL type "' . $type . '" is not supported!');
        }
    }


    /**
     * Creates a Date validator
     * TODO timestamp
     * @param $type
     * @return DateValidator
     */
    private static function _date($type)
    {
        $format = "Y-m-d H:i:s";

        switch ($type) {
            case "date":
                $format = "Y-m-d";
                break;
            case "datetime":
                $format = "Y-m-d H:i:s";
                break;
            case "time":
                $format = "H:i:s";
                break;
            case "year":
                $format = "Y";
                break;

        }

        return new DateValidator(array(
            'format' => $format,
            'message' => 'Date Format',
            'allowEmpty' => true
        ));
    }


    /**
     * Creates a StringLength validator
     * @param $options
     * @return StringLength
     */
    private static function _strings($type, $options)
    {

        //TODO not really correct, 2bytes caracters
        switch ($type) {
            case'tinytext':
                $options['length'] = 255;
                break;
            case'text':
                $options['length'] = 65535;
                break;
            case'mediumtext':
                $options['length'] = 16777215;
                break;
            case'longtext':
                $options['length'] = 4294967295;
                break;
        }

        return new StringLengthValidator(array(
            'max' => $options['length'],
            'min' => 0,
            'message' => 'We don\'t like really long strings(' . $options['length'] . ')'
        ));
    }

    /**
     * Creates a Between validator
     * @param $type
     * @param $options
     * @return Between
     */
    private static function _int($type, $options)
    {
        $unsigned = $options['unsigned'];
        $min = 0;
        $max = 0;

        switch (strtoupper($type)) {
            case "TINYINT":
                $min = ($unsigned) ? 0 : -128;
                $max = ($unsigned) ? 255 : 127;
                break;
            case "SMALLINT":
                $min = ($unsigned) ? 0 : -32768;
                $max = ($unsigned) ? 65535 : 32767;
                break;
            case "MEDIUMINT":
                $min = ($unsigned) ? 0 : -8388608;
                $max = ($unsigned) ? 16777215 : 8388607;
                break;
            case "INT":
                $min = ($unsigned) ? 0 : -2147483648;
                $max = ($unsigned) ? 4294967295 : 2147483647;
                break;
            case "BIGINT":
                $min = ($unsigned) ? 0 : -9223372036854775808;
                $max = ($unsigned) ? 18446744073709551615 : 9223372036854775807;
                break;
        }

        return new BetweenValidator(array(
            'minimum' => $min,
            'maximum' => $max,
            'message' => 'The value must be between ' . $min . ' and ' . $max,
            'allowEmpty' => true
        ));
    }

}