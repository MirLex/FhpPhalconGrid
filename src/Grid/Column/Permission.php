<?php
/**
 * Security
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid\Column;

class Permission
{

    private $edit = true;
    private $add = true;
    private $remove = true;

    //TODO
    private $callback = null;

    public function getRules($onlyEdit=null){
        if($onlyEdit!==null){
            return array('edit'=>$this->getEdit());
        }
        return array('edit'=>$this->getEdit(),'add'=>$this->getAdd(),'remove'=>$this->getRemove());
    }

    /**
     * @return bool
     */
    public function getRemove()
    {
        return $this->remove;
    }

    /**
     * @param Config $remove
     */
    public function allowRemove(Config $remove)
    {
        $this->remove = $remove->getValue();
        return $this;
    }

    /**
     * @return bool
     */
    public function getAdd()
    {
        return $this->add;
    }

    /**
     * @param Config $add
     */
    public function allowAdd(Config $add)
    {
        $this->add = $add->getValue();
        return $this;

    }

    /**
     * @return bool
     */
    public function getEdit()
    {
        return $this->edit;
    }

    /**
     * @param Config $edit
     */
    public function allowEdit(Config $edit)
    {
        $this->edit = $edit->getValue();
        return $this;

    }


}