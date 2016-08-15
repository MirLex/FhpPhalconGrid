<?php
/**
 * Router
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Helper;

use Phalcon\Mvc\User\Plugin;

class Router extends Plugin
{

    /**
     * @param string $name
     * @param bool $pattern
     * @return string|array
     */
    public function getParamsByRouteName($name, $pattern = false)
    {
        $routeParams = false;

        $routes = $this->getDI()->get('router')->getRoutes();
        /** @var \Phalcon\Mvc\Router\RouteInterface $route */
        foreach ($routes as $route) {
            if ($name == $route->getName()) {
                if ($pattern === true) {
                    return $route->getPattern();
                }
                return $route->getPaths();
            }
        }

        return $routeParams;
    }

    public function getRouteByControllerAndActionAndParam($controller, $action)
    {
        $routes = $this->getDI()->get('router')->getRoutes();
        /** @var \Phalcon\Mvc\Router\Route $route */
        foreach ($routes as $route) {
            $paths = $route->getPaths();
            if (isset($paths['params']) AND $paths['controller'] == $controller AND $paths['action'] == $action) {
                return $route->getRouteId();
            }
        }
        return false;
    }
}