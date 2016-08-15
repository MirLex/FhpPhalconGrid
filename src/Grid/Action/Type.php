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
     * a different image can be set
     * @var String
     */
    private $image = null;

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

    /**
     * @return String
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param String $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    public function addBehavior($behavior){
        $this->behavior[] = $behavior;
    }

    public function getBehaviors(){
        return (count($this->behavior)!=0?$this->behavior:false);
    }

}

?>