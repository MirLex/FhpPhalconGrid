<?php
/**
 * FloatValidator
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

class FloatValidator extends ValidatorAbstract
{
    public function validate(Validation $validator, $attribute)
    {

        if ($this->getOption('allowEmpty') === true && $validator->getValue($attribute) == null) {
            return true;
        }

        $value = $validator->getValue($attribute);

        if (!is_int($value) && !is_float($value)) {
            $message = $this->getOption('message');

            if (!$message) {
                $message = 'The number is invalid!';
            }

            $validator->appendMessage(new Message($message, $attribute, 'Float'));

            return false;
        }

        return true;
    }
}