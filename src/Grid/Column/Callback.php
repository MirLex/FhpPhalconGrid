<?php
/**
 * Callback
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Grid\Column;

class Callback
{

    static public function checkbox($field, $group, $grid)
    {
        $results = $grid->getResult();


        if ($group) {
            foreach ($results->{$group} as $rowCount => $entry) {
                foreach ($entry as $column => $value) {
                    if ($column == $field) {
                        $results->{$group}[$rowCount][$column] = ($value == 1 ? 'Y' : 'N');
                    }
                }
            }
        } else {
            foreach ($results as $row => $value) {
                $results[$row][$field] = ($value[$field] == 1 ? 'Y' : 'N');
            }
        }


        return $results;
    }

    static public function datetime($field, $group, $grid)
    {
        $results = $grid->getResult();

        if ($group) {
            foreach ($results->{$group} as $rowCount => $entry) {
                foreach ($entry as $column => $value) {
                    if ($column == $field) {
                        if ($value) {
                            $date = new \DateTime($value);
                            $results->{$group}[$rowCount][$column] = array('date' => $date->format('Y-m-d'), 'time' => $date->format('H:i:s'));
                        } else {
                            $results->{$group}[$rowCount][$column] = array('date' => null, 'time' => null);

                        }

                    }
                }

            }
        } else {
            foreach ($results as $row => $value) {
                if ($row == $field) {
                    if ($value) {
                        $date = new \DateTime($value);
                        $results->{$row} = array('date' => $date->format('Y-m-d'), 'time' => $date->format('H:i:s'));
                    } else {
                        $results->{$row} = array('date' => null, 'time' => null);
                    }

                }
            }
        }
        return $results;
    }

    static public function hasOneRender($field, $group, $grid)
    {

        $results = $grid->getResult();

        if($grid->columnExists($field)){
            $fValues = $grid->getColumn($field)->getFieldValue();
            foreach ($results as $row => $value) {
                if (isset($fValues[$value[$field]])) {
                    $value[$field] = $fValues[$value[$field]];
                }
                $results[$row] = $value;
            }
        }

        return $results;
    }


    /**
     * Return the right action links
     *
     * @param String $field
     * @param \FhpPhalconGrid\Grid\Grid $grid
     * @return array
     */
    static public function groupRender($field, $group, $grid)
    {


        $results = $grid->getResult();

        $group = $grid->getColumn($field);
        $columnSeparator = $group->getColumnSeparator();
        $lineSeparator = $group->getLineSeparator();

        //get the columns of the group
        $columnNames = array();
        foreach ($group->getColumnsForSelect() as $column) {
            $columnNames[] = $column->getField();
        }


        if ($group->getConnectionTable()) {

            $column = array_keys($group->getColumns());
            $fValues = $group->getColumn($column[0])->getFieldValue();

            if (is_array($results)) {
                foreach ($results as $row => $array) {
                    $values = explode(',', $results[$row][$field]);
                    $newVal = [];
                    foreach ($values as $val) {
                        $newVal[] = (isset($fValues[$val]) ? $fValues[$val] : $val);
                    }
                    $results[$row][$field] = implode(', ', $newVal);
                }
            }

            return $results;
        }


   

        //modify the result, to return an array (GRID view)

        if (is_array($results)) {
            foreach ($results as $row => $array) {
                $groupRows = explode($lineSeparator . ',', substr($array[$field], 0, strrpos($array[$field], $lineSeparator))); //deleting last linebreaker the last comma is for the mysql behaviour TODO replace that!
                $val = array();
                foreach ($groupRows as $groupRow) {
                    $values = explode($columnSeparator, $groupRow);
                    if (count($values) == 1 && $values[0] == '') {
                        $values = array_fill(0, count($columnNames), '');
                    }
                    $rv = array_combine($columnNames, $values);
                    $val[] = $rv;

                }
                $results[$row][$field] = ($val === false) ? array() : $val;

            }
        } else {
            //(DETAILS + EDIT view)
            $array = $results;

            if ($array) {
                $groupRows = explode($lineSeparator . ',', substr($array->{$field}, 0, strrpos($array->{$field}, $lineSeparator))); //deleting last linebreaker the last comma is for the mysql behaviour TODO replace that!
                $val = array();
                foreach ($groupRows as $groupRow) {
                    $values = explode($columnSeparator, $groupRow);
                    //if there is no result in the sql
                    if (count($values) == 1 && $values[0] == '') {
                        $values = array_fill(0, count($columnNames), '');
                    }
                    $rv = array_combine($columnNames, $values);
                    $val[] = $rv;
                }
                $results->{$field} = ($val === false) ? array() : $val;
            }

        }

        return $results;
    }
}


