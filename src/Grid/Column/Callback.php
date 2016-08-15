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
    /**
     * Return the right action links
     *
     * @param String $field
     * @param \FhpPhalconGrid\Grid\Grid $grid
     * @return array
     */
    static public function groupRender($field, $grid)
    {

        $results = $grid->getResult();

        $group = $grid->getColumn($field);
        $columnSeparator = $group->getColumnSeparator();
        $lineSeparator = $group->getLineSeparator();

        //get the columns of the group
        $columnNames = array();
        foreach ($group->getColumnsForSelect() as $column) {
            $columnNames[] = $column->getName();
        }



        //modify the result, to return an array (GRID view)
        if (is_array($results)) {
            foreach ($results as $row => $array) {
                $groupRows = explode($lineSeparator.',', substr($array[$field], 0, strrpos($array[$field], $lineSeparator ))); //deleting last linebreaker the last comma is for the mysql behaviour TODO replace that!
                $val = array();
                foreach ($groupRows as $groupRow) {
                    $values = explode($columnSeparator, $groupRow);
                    if(count($values) == 1 && $values[0]==''){
                        $values=array_fill(0, count($columnNames), '');
                    }
                    $rv =array_combine($columnNames, $values);
                    $val[]=$rv;

                }
                $results[$row][$field] =($val===false)?array():$val;

            }
        } else {
            //(DETAILS + EDIT view)
            $array = $results;
            $groupRows = explode($lineSeparator.',', substr($array->{$field}, 0, strrpos($array->{$field}, $lineSeparator ))); //deleting last linebreaker the last comma is for the mysql behaviour TODO replace that!


            //var_dump($groupRows);
            $val = array();
            foreach ($groupRows as $groupRow) {
                $values = explode($columnSeparator, $groupRow);
                //if there is no result in the sql
                if(count($values) == 1 && $values[0]==''){
                    $values=array_fill(0, count($columnNames), '');
                }
                $rv =array_combine($columnNames, $values);
                $val[]=$rv;
            }

            $results->{$field} =($val===false)?array():$val;

        }

        return $results;
    }
}


