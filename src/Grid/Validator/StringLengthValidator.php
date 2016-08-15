<?php
/**
 * StringLengthValidator
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;

class StringLengthValidator extends Validator\StringLength
{
    public function getOptions()
    {
        return $this->_options;
    }
}