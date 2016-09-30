<?php
/**
 * Type
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Action;

use FhpPhalconGrid\Grid\Action;

class Type
{

    private $name = null;

    private $postion = 10;

    /**
     * @return null
     */
    public function getPostion()
    {
        return $this->postion;
    }

    /**
     * @param null $postion
     */
    public function setPostion($postion)
    {
        $this->postion = $postion;
        return $this;
    }

    /**
     * @return null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param null $name
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return null
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param null $icon
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;

    }
    private $icon = null;
    /**
     * set if a action is visible or not
     * @var bool
     */
    private $visible = true;

    /**
     * a customized link can bet set
     * @var String
     */
    private $forUrl = Action::ROUTE;

    /**
     * Behaviors for delete
     * @var array
     */
    private $behavior=array();

    /**
     * @return boolean
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param boolean $visible
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return String
     */
    public function getForUrl()
    {
        return $this->forUrl;
    }

    /**
     * @param String $forUrl
     * @return $this
     */
    public function setForUrl($forUrl)
    {
        $this->forUrl = $forUrl;
        return $this;
    }

    public function addBehavior($behavior){
        $this->behavior[] = $behavior;
    }

    public function getBehaviors(){
        return (count($this->behavior)!=0?$this->behavior:false);
    }

}

?>