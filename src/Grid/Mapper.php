<?php
/**
 * Mapper
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid;

use FhpPhalconGrid\Grid\Mapper\Entity;
use FhpPhalconGrid\Grid\Mapper\Exception;
use Phalcon\Annotations\Adapter\Memory;
use Phalcon\DiInterface;
use Phalcon\Mvc\User\Component;

class Mapper extends Component
{
    /** @var Entity[] $entities */
    private $entities;
    /** @var array $tableToModel */
    private $tableToModel;
    /** @var array $aliasToModel */
    private $aliasToModel;
    /** @var  array $columns */
    private $columns;

    /**
     * @param $di
     * @param null|String $dir
     * @throws Exception
     */
    public function __construct(DiInterface $di, $dir = null)
    {
        $this->setDI($di);

        foreach (array('cache', 'db', 'config') as $service) {
            if (!$this->getDI()->has($service)) {
                throw new Exception('Please initialize the ' . $service . ' service!');
            }
        }

        $this->_buildEntityMapper($this->_readEntityDir($dir));
    }

    public function addColumnsToEntity($columns)
    {
        foreach ($columns as $col) {
            if ($col->getName() == Action::COLUMNALIAS) {
                continue;
            }

            $remove = false;
            $permission = $col->getPermission();
            if ($col->isRemove('edit') == true OR $permission['edit'] == false) {
                $remove = true;
            }
            if (!$col->isGroup()) {
                $this->columns[$col->getModelName()][$col->getField()]['remove'] = $remove;
            } else {
                $this->addColumnsToEntity($col->getColumns());
            }
        }
    }

    /**
     * @param $entity
     * @param $options
     * @return mixed
     * @throws Exception
     */
    public function getColumnsOfEntity($entity, $noRemovedColumns = null)
    {

        if (is_object($entity)) {
            $entity = get_class($entity);
        }
        if (!isset($this->columns[$entity])) {
            throw new Exception('This Entity "' . $entity . '" is unknown!');
        }

        if ($noRemovedColumns !== null) {
            return array_keys(array_filter($this->columns[$entity], function ($var) {
                return ($var['remove'] == false);
            }));
        }

        return array_keys($this->columns[$entity]);
    }


    public function getKeysToHideFromModelName($model)
    {
        $keys = array();

        if (!isset($this->entities[$model])) {
            throw new Exception('The model "' . $model . '" is unknown!');
        }

        if (count($this->entities[$model]->getPrimary()) > 0) {
            $keys = array_merge($keys, $this->entities[$model]->getPrimary());
        }
        if (count($this->entities[$model]->getRelatedFields()) > 0) {
            $keys = array_merge($keys, $this->entities[$model]->getRelatedFields());
        }

        return $keys;
    }

    public function getPrimaryAndUniqueKey($from)
    {

        $keys = array();

        if (is_array($from)) {
            foreach ($from as $table) {
                $model = $this->getModelNameFromAlias($table);
            }
        } else {
            $model = $this->getModelNameFromAlias($from);
        }

        if (!isset($this->entities[$model])) {
            throw new Exception('The model "' . $model . '" is unknown!');
        }

        foreach ($this->entities[$model]->getColumns() as $column => $entity) {
            if ($entity['key'] !== false) {
                $keys[$this->entities[$model]->getPhqlName()][] = $column;
            }
        }

        return $keys;
    }

    /**
     * @param Entity $entity
     * @return $this
     */
    private function addEntity(Entity $entity)
    {
        $this->entities[$entity->getName()] = $entity;
        return $this;
    }

    /**
     * @param String $entity
     * @return Entity
     * @throws Exception
     */
    public function getEntity($entity)
    {
        if (!isset($this->entities[$entity])) {
            throw new Exception('The entity "' . $entity . '" does not exist!');
        }
        return $this->entities[$entity];
    }

    /**
     * Returns the schema name of the database.
     * If there doesnt exist one, the default schema is taken
     *
     * @param String $schema
     * @return String
     */
    public function getSchemaName($schema)
    {
        if (empty($schema)) {
            $schema = $this->getDI()->get('db')->getDescriptor()['dbname'];
        }
        return $schema;
    }

    /**
     * Returns the Model name of a database table
     *
     * @param String $table
     * @return String
     */
    public function getModelNameFromTable($table)
    {
        if (!isset($this->tableToModel[$table])) {
            return $table;
        }
        return $this->tableToModel[$table];
    }

    /**
     * Returns the Model name of a database alias
     *
     * @param String $alias
     * @return String
     */
    public function getModelNameFromAlias($alias)
    {
        $table = $alias;
        if (isset($this->aliasToModel[$alias])) {
            $table = $this->aliasToModel[$alias];
        }

        return $this->getModelNameFromTable($table);
    }

    public function addAlias($alias, $model)
    {
        $this->aliasToModel[$alias] = $model;

        return $this;
    }

    /**
     * Add one or more aliases to a table name
     * @param array $query
     */
    public function addAliasFromQuery($query)
    {

        foreach ($query as $key => $value) {
            if ($key === "source") {
                if (isset($value[2])) {
                    $this->aliasToModel[$value[2]] = $value[0];
                }
            }
            if ($key === "tables") {
                foreach ($value as $table) {
                    if (is_array($table) AND isset($table[2])) {
                        $this->aliasToModel[$table[2]] = $table[0];
                    }
                }
            }

            if (is_array($value)) {
                $this->addAliasFromQuery($value);
            }
        }
    }


    public function _readControllerDir($dirs = null)
    {
        if ($dirs === null) {
            $dirs = $this->getDI()->get('config')->get('application')->controllersDir;
        }

        $cache = $this->getDI()->get('cache');
        if ($cache->get('CONTROLLER_' . md5(serialize($dirs))) === null) {
            $reader = new Memory();
            foreach ($dirs as $dir) {
                if (strpos($dir, -1) != "/") {
                    $dir .= '/';
                }
                $rv = array();
                foreach ($this->_readDir($dir) as $controller) {
                    $reflector = $reader->get($controller);
                    foreach (get_class_methods($controller) as $actions) {
                        if (strpos($actions, 'Action') > 0) {
                            $rv[$controller][$actions] = array('grid' => false);
                            $annotations = $reader->getMethod($controller, $actions);
                            if (count($annotations) > 0) {
                                foreach ($annotations as $annotation) {
                                    if ($annotation->getName() == "Grid") {
                                        $rv[$controller][$actions] = array('grid' => true);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $cache->save('CONTROLLER_' . md5(serialize($dirs)), serialize($rv));
        } else {
            $rv = unserialize($cache->get('CONTROLLER_' . md5(serialize($dirs))));
        }

        return $rv;
    }

    private function _readDir($dir)
    {


        $cache = $this->getDI()->get('cache');
        if ($cache->get('DIR_' . md5($dir)) === null) {


            $entities = array();
            $files = scandir($dir);


            foreach ($files as $file) {
                $ns = NULL;
                $class = null;

                if (is_file($dir . $file)) {

                    $handle = fopen($dir . $file, "r");


                    if ($handle) {
                        while (($line = fgets($handle)) !== false) {

                            if (strpos($line, 'namespace') === 0) {
                                $parts = explode(' ', $line);
                                $ns = rtrim(trim($parts[1]), ';');
                                break;
                            }
                        }


                        $handle = fopen($dir . $file, "r");
                        while (($line = fgets($handle)) !== false) {

                            if (strpos($line, 'class') === 0) {
                                $parts = explode(' ', $line);

                                $class = rtrim(trim($parts[1]), ';');
                                break;
                            }
                        }
                        fclose($handle);
                    }


                    if ($ns === null AND $class === null) {
                        continue;
                    }


                    $entities[] = $ns . ($ns ? '\\' : '') . $class;
                }
            }


            $cache->save('DIR_' . md5($dir), serialize($entities));
        } else {
            $entities = unserialize($cache->get('DIR_' . md5($dir)));
        }


        return $entities;
    }

    /**
     * read the entity dir and save the array in the cache
     * @param null|String $dir
     * @return array
     */
    private function _readEntityDir($dir = null)
    {

        if ($dir === null) {
            $dir = $this->getDI()->get('config')->get('application')->entitiesDir;
        }


        if (strpos($dir, -1) != "/") {
            $dir .= '/';
        }


        $cache = $this->getDI()->get('cache');
        if ($cache->get('ENTITY_' . md5($dir)) === null) {

            $entities = array();

            foreach ($this->_readDir($dir) as $entityClass) {
                /** @var \Phalcon\Mvc\Model $entity */
                $entity = new $entityClass();
                $schema = $this->getSchemaName($entity->getSchema());
                $columns = $this->_describeTable(Entity::getTableNameWithSchema($schema, $entity->getSource()));
                $entities[$entityClass] = array(
                    'relatedFields' => array(),
                    'primary' => array(),
                    'schema' => $schema,
                    'table' => $entity->getSource(),
                    'columns' => $columns
                );


                if ($entity !== null) {
                    //add primary keys
                    foreach ($columns as $k => $column) {
                        if ($column['key']) {
                            $entities[$entityClass]['primary'][] = $k;
                        }
                    }


                    if ($this->modelsManager->getRelations($entityClass)) {
                        foreach ($this->modelsManager->getRelations($entityClass) as $model) {
                            $entities[$entityClass]['relations'][$model->getReferencedModel()] = $model;
                        }

                    }

                    //add related keys
                    if ($this->modelsManager->getBelongsTo($entity)) {
                        foreach ($this->modelsManager->getBelongsTo($entity) as $model) {
                            $entities[$entityClass]['relatedFields'][] = $model->getFields();
                        }

                    }
                }
            }
            $cache->save('ENTITY_' . md5($dir), serialize($entities));
        } else {
            $entities = unserialize($cache->get('ENTITY_' . md5($dir)));
        }

        return $entities;
    }


    /**
     * @param array $entities
     */
    private
    function _buildEntityMapper(array $entities)
    {
        foreach ($entities as $entityName => $options) {
            $this->tableToModel[$options['table']] = $entityName;

            $entityToTableMapper = new Entity();
            $entityToTableMapper->setName($entityName)->setTable($options['table'])->setSchema($options['schema'])->setColumns($options['columns'])->setPrimary($options['primary'])->setRelations((isset($options['relations'])?$options['relations']:null))->setRelatedFields($options['relatedFields']);
            $this->addEntity($entityToTableMapper);
        }
    }

    /**
     * @param String $table
     * @return array
     */
    private
    function _describeTable($table)
    {
        $sourceName = __NAMESPACE__ . "\\Source\\" . ucfirst($this->getDI()->get('db')->getType());
        /** @var \FhpPhalconGrid\Grid\Source\SourceInterface $source */
        $source = new $sourceName($table, $this->getDI());
        return $source->getColumns();
    }
}

?>