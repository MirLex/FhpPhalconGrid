<?php
/**
 * Config
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Grid\Column;

class Config
{

    private $value = null;
    private $roles = array();

    public function __construct($value, $roles)
    {
        $this->value = $value;
        $this->roles = $roles;
    }

    /**
     * @return array
     */
    public function getValue()
    {
        if (count($this->roles)==1 AND $this->roles[0] == '*') {
            return $this->value;
        }

        if (count($this->roles)>0) {
            $user = \FhpPhalconAuth\Service\User::getInstance();

        

            if ($this->value === true AND isset($user->data->roles) AND array_intersect($user->data->roles, $this->roles)) {
                return $this->value;
            }
            return ($this->value === true ? false : true);
        }

        return $this->value;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param array $roles
     * @return $this
     */
    public function setRoles(array $roles)
    {
        $this->roles = $roles;
        return $this;
    }
}