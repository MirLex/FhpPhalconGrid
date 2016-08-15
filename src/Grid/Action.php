<?php
/**
 * Action
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid;

use FhpPhalconGrid\Grid\Action\Exception;
use FhpPhalconGrid\Grid\Action\Type;
use FhpPhalconGrid\Helper\Router;
use Phalcon\DiInterface;
use Phalcon\Mvc\User\Component;

class Action extends Component
{

    /** Name of the route */
    const ROUTE = 'grid_action_route';

    /** Name of the sql column */
    const COLUMNALIAS = 'grid_action';

    /** @var Type[] */
    private $types = array();

    /**
     * the primary or manually added search keys
     * @var array
     */
    private $keys = array();

    /**
     * get the information if the user added keys manually
     * @var bool
     */
    private $changed = false;

    /**
     * get link params with key and value
     * @var bool|array
     */
    private $linkParams = false;

    //TODO
    private $callback = null;

    private $params;

    /**
     * sets the di and create the detail,edit and delete actions
     * @param DiInterface $di
     * @throws Exception
     */
    public function __construct(DiInterface $di)
    {
        $this->setDI($di);

        foreach (array('dispatcher', 'router', 'url') as $service) {
            if (!$this->getDI()->has($service)) {
                throw new Exception('Please initialize the ' . $service . ' service!');
            }
        }

        $this->_addTypes(Grid::DETAILS)
            ->_addTypes(Grid::EDIT)
            ->_addTypes(Grid::DELETE);
    }

    /**
     * @return array
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     *
     * @param array $keys
     * @return $this
     */
    public function setKeys(array $keys)
    {
        $this->keys = $keys;
        $this->changed = true;
        return $this;
    }

    /**
     * get link pattern
     * @param bool 
     * @return array
     */
    public function getLinkPattern($angular = false)
    {
        $url = $this->getDI()->get('url');
        $links = array();

        /** @var Type $type */
        foreach ($this->types as $mode => $type) {
            if ($type->isVisible()) {
                $options = array('for' => $type->getForUrl(), Grid::MODE => $mode);
                $i = 0;
                foreach ($this->params as $k => $param) {
                    $options[$k] = ':'.$i.':' ;

                    if($angular===true){
                        $key = explode('.',$k);
                        $options[$k] = ':'.$key[1];
                    }
                    $i++;
                }

                $links[$mode] = $url->get($options);
            }
        }
        return $links;
    }


    /**
     * get array with field name => value for
     * edit,details,delete
     *
     * @return array
     */
    public function getLinkParams()
    {
        return $this->linkParams;
    }

    /**
     * @return bool
     */
    public function hasChanged()
    {
        return $this->changed;
    }

    /**
     * @return bool
     */
    public function isRemoved()
    {
        /** @var Type $type */
        foreach ($this->types as $type) {
            if ($type->isVisible()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param bool $remove
     * @return $this
     */
    public function setRemove($remove)
    {
        foreach ($this->types as $type) {
            $type->setVisible($remove);
        }
        return $this;
    }

    /**
     * @param String $type
     * @return Type
     * @throws Exception
     */
    public function getType($type)
    {
        if (!in_array($type, array(Grid::DETAILS, Grid::EDIT, Grid::DELETE))) {
            throw new Exception('This action type "' . $type . '" is not allowed!');
        }
        return $this->types[$type];
    }

    /**
     * @param String $type
     * @return $this
     */
    protected function _addTypes($type)
    {
        $this->types[$type] = new Type($this->getDI());
        return $this;
    }

    /**
     * @return null
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param null $callback
     * @return $this
     */
    public function setCallback($callback)
    {
        //TODO set event Query after Fetch
        $this->callback = $callback;
        return $this;
    }

    /**
     * create the phql field for the action column
     *
     * @param null|\Phalcon\Mvc\Model\Query\Builder $query
     * @return String|\Phalcon\Mvc\Model\Query\Builder
     */
    public function createActionColumn($query = null)
    {
        $columns = array();
        $params = array();

        $i = 2;
        foreach ($this->getKeys() as $model => $col) {
            if (is_string($col)) {
                $columns[] = $model . '.' . $col;
                $params[$model . '.' . $col] = $i;
            }
            if (is_array($col)) {
                foreach ($col as $c) {
                    $columns[] = $model . '.' . $c;
                    $columns[] = '"/"';
                    $params[$model . '.' . $c] = $i;
                    $i++;
                }
            }
        }
        $this->params = $params;

        if (Grid::getMode() != Grid::TABLE) {
            $this->_setDispatcherParams();
        }

        //reset changed variable
        $this->changed = false;

        if ($query === null) {
            return 'CONCAT(' . implode(',', $columns) . ') as ' . self::COLUMNALIAS;
        }

        $phql = $query->columns(array_merge(array('CONCAT(' . implode(',', $columns) . ') as ' . self::COLUMNALIAS), $query->getColumns()));
        return $phql;
    }

    /**
     * reConfig the matched route and sets the url params
     */
    protected function _setDispatcherParams()
    {
        /** @var \Phalcon\Mvc\Dispatcher $dispatcher */
        $dispatcher = $this->getDI()->get('dispatcher');

        $defaultConfig['controller'] = $dispatcher->getControllerName();
        $defaultConfig['action'] = $dispatcher->getActionName();
        $defaultConfig[Grid::MODE] = 1;
        $params = array_merge($defaultConfig, $this->params);



        $helper = new Router();
        $routeId = $helper->getRouteByControllerAndActionAndParam($dispatcher->getControllerName(), $dispatcher->getActionName());

        /** @var \Phalcon\Mvc\Router $router */
        $router = $this->getDI()->get('router');
        $route = $router->getRouteById($routeId);


        $route->reConfigure(str_replace('/:params', '', $route->getPattern()) . str_repeat('/([a-zA-Z0-9\_\-]+)', count($params) - 2), $params);
        $route->setName(self::ROUTE);

        $dispatchParams = $dispatcher->getParams();
        foreach ($params as $name => $key) {
            if (isset($dispatchParams[$key - 1])) {
                $dispatcher->setParam($name, $dispatchParams[$key - 1]);
                $this->linkParams[$name] = $dispatchParams[$key - 1];


            }
        }
    }
}

?>