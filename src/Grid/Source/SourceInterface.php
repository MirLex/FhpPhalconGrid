<?php
/**
 * SourceInterface
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Source;

interface SourceInterface
{
    public function getColumns();

    static public function getValidator($type,$options);
}

?>