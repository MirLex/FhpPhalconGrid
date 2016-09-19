<?php
/**
 * Abstract
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */
namespace FhpPhalconGrid\Grid\Column;

use FhpPhalconGrid\Grid\Grid;

abstract class AbstractColumn
{

    private $sqlRemove = true;
    private $connectionTable = false;

    /**
     * Name of the column
     * @var array
     */
    private $name = array(Grid::TABLE => null, Grid::DETAILS => null, Grid::EDIT => null);

    /**
     * define if a column is hidden
     * @var array
     */
    private $hidden = array(Grid::TABLE => false, Grid::DETAILS => false, Grid::EDIT => false);

    /**
     * define if a column should get removed
     * @var array
     */
    private $remove = array(Grid::TABLE => false, Grid::DETAILS => false, Grid::EDIT => false);

    /**
     * Callback function which is called after fetch
     * @var array
     */
    private $callback = array(Grid::TABLE => false, Grid::DETAILS => false, Grid::EDIT => false);

    /**
     * define if a column should be filterable
     * @var bool
     */
    private $filterable = true;

    /**
     * define if a column should be sortable
     * @var bool
     */
    private $sortable = true;

    /**
     * define if its a group or a single column
     * @var bool
     */
    private $group = false;


    /**
     * the position of the column
     * @var array
     */
    private $position = array(Grid::TABLE => null, Grid::DETAILS => null, Grid::EDIT => null);

    /**
     * Field name of the column like it is in the query
     * @var null|String
     */
    private $field = null;




    public function getConnectionTable(){
        return $this->connectionTable;
    }
    public function setConnectionTable(ConnectedTable $cTable){
        $this->connectionTable = $cTable;
        return $this;
    }

    public function isGroup()
    {
        return $this->group;
    }

    /**
     * @return null|String
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param null $field
     * @return $this
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @param String $mode
     * @return mixed
     */
    public function getCallback($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }
        return $this->callback[$mode];
    }

    /**
     * @param mixed $callback
     * @param null|String $mode
     * @return $this
     * @throws \FhpPhalconGrid\Grid\Exception
     */
    public function setCallback($callback, $mode = null)
    {
        $this->callback = $this->_setModeOptions($this->callback, $callback, $mode);
        return $this;
    }

    /**
     * @param String $mode
     * @return bool
     */
    public function isRemove($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }
        if (is_bool($this->remove[$mode])) {
            return $this->remove[$mode];
        }
        return $this->remove[$mode]->getValue();
    }

    /**
     * @param $value
     * @param array $roles
     * @param null $mode
     * @return $this
     * @throws Exception
     */
    public function setRemove($value, $roles = array(), $mode = null)
    {
        $config = new Config($value, $roles);
        $this->remove = $this->_setModeOptions($this->remove, $config, $mode);
        return $this;
    }

    /**
     * @param String $mode
     * @return bool
     */
    public function isHidden($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }
        if (is_bool($this->hidden[$mode])) {
            return $this->hidden[$mode];
        }
        return $this->hidden[$mode]->getValue();
    }

    /**
     * @param $value
     * @param array $roles
     * @param null $mode
     * @return $this
     * @throws Exception
     */
    public function setHidden($value, $roles = array(), $mode = null)
    {
        $config = new Config($value, $roles);
        $this->hidden = $this->_setModeOptions($this->hidden, $config, $mode);
        return $this;
    }

    /**
     * @param String $mode
     * @return int
     */
    public function getPosition($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }
        return $this->position[$mode];
    }

    /**
     * @param int $position
     * @param null|String $mode
     * @return $this
     * @throws \FhpPhalconGrid\Grid\Exception
     */
    public function setPosition($position, $mode = null)
    {
        $this->position = $this->_setModeOptions($this->position, $position, $mode);
        return $this;
    }

    /**
     * @var Permission
     */
    protected $permission;


    /**
     * @return Permission
     */
    public function getPermission()
    {
        return $this->permission->getRules();
    }

    public function setPermission(Permission $permission){
        $this->permission = $permission;
        return $this;
    }


    /**
     * Column constructor.
     */
    public function __construct()
    {
        $this->permission = new Permission();
    }


    /**
     * @return boolean
     */
    public function isFilterable()
    {
        return $this->filterable;
    }

    /**
     * @param boolean $filter
     * @return $this
     */
    public function setFilterable($filter)
    {
        $this->filterable = $filter;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isSortable()
    {
        return $this->sortable;
    }

    /**
     * @param boolean $sort
     * @return $this
     */
    public function setSortable($sort)
    {
        $this->sortable = $sort;
        return $this;
    }


    /**
     * @param $mode
     * @return null|String
     */
    public function getName($mode = null)
    {
        if ($mode === null) {
            $mode = Grid::getMode();
        }
        return $this->name[$mode];
    }

    /**
     * @param string $name
     * @param null $mode
     * @return $this
     * @throws \FhpPhalconGrid\Grid\Exception
     */
    public function setName($name, $mode = null)
    {
        $this->name = $this->_setModeOptions($this->name, $name, $mode);
        return $this;
    }

    protected function _setModeOptions($field, $value, $mode)
    {

        $types = array(Grid::TABLE, Grid::DETAILS, Grid::EDIT);
        if ($mode === null) {
            foreach ($types as $mode) {
                $field[$mode] = $value;
            }
        } else {
            if (!in_array($mode, $types)) {
                throw new Exception('This mode "' . $mode . '" is not allowed!');
            }
            $field[$mode] = $value;
        }
        return $field;
    }

    /**
     * @return bool
     */
    public function isSqlRemove()
    {
        if ($this->sqlRemove === true AND $this->isRemove()) {
            return true;
        }
        return false;
    }

    /**
     * @param boolean $sqlRemove
     */
    public function setSqlRemove($sqlRemove)
    {
        $this->setRemove($sqlRemove);
        $this->sqlRemove = $sqlRemove;
        return $this;
    }
}