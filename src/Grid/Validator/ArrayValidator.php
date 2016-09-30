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

        if($this->getOption('allowEmpty')===true && $validator->getValue($attribute)==null){
            return true;
        }

        $arr = explode(',',$this->getOption('options')['length']);
        $values = explode(',',$validator->getValue($attribute));


        foreach($values as $val){
            if(!in_array($val,$arr)){

                $message = $this->getOption('message');

                if (!$message) {
                    $message = 'The value is invalid!';
                }

                $validator->appendMessage(new Message($message, $attribute, 'Array'));

                return false;
            }
        }


        return true;

    }
}