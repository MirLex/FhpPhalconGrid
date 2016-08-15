<?php
/**
 * ArrayValidator
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
use Phalcon\Validation\ValidatorInterface;

class ArrayValidator extends ValidatorAbstract
{
    public function validate(Validation $validator, $attribute)
    {
        $date = date_parse_from_format('d/m/Y', $validator->getValue($attribute));
        if (($date['error_count'] + $date['warning_count']) > 0) {
            $message = $this->getOption('message');

            if (!$message) {
                $message = 'The date entered was in valid, must be in format dd/mm/yyyy';
            }

            $validator->appendMessage(new Message($message, $attribute, 'Date'));

            return false;
        }

        return true;
    }
}