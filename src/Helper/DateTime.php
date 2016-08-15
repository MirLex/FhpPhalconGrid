<?php
/**
 * DateTime
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Helper;

class DateTime
{
    const DB = 'db';

    /**
     * @param null|string $type
     * @param null|string $options
     * @return string
     * @throws Exception
     */
    static public function getFormat($type = null, $options = null)
    {
        $format = 'Y-m-d H:i:s';

        if ($type == "db") {

            if ($options == null) {
                throw new Exception('The options are missing!');
            }

            switch ($options) {
                case 'mysql':
                    $format = 'Y-m-d H:i:s';
                    break;
            }
        }

        return $format;
    }

}