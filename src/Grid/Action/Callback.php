<?php
/**
 * Callback
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Grid\Action;

class Callback
{
    /**
     * Return the right action links
     *
     * @param String $field
     * @param \FhpPhalconGrid\Grid\Grid $grid
     * @return Array
     */
    static public function render($field, $grid)
    {

        $results =$grid->getResult();
        $keys = $grid->getAction()->getKeys();

        foreach ($results as $row => $array) {
            //TODO make a method for a set and get for that together with the setting it up in the mysql db
            $params = explode('/', $array[$field]);
            $arr=[];
            foreach($keys as $entityObject){
                foreach($entityObject as $key=>$value){
                    $arr[$value] = $params[$key];
                }
            }

            $results[$row][$field] = $arr;
        }
        return $results;
    }
}