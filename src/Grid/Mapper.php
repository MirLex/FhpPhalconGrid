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
        if ($cache->get('MAPPER_' . md5($dir)) === null) {

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
                        while (($line = fgets($handle)) !== false) {
                            if (strpos($line, 'class') === 0) {
                                $parts = explode(' ', $line);
                                $class = rtrim(trim($parts[1]), ';');
                                break;
                            }
                        }
                        fclose($handle);
                    }


                    $entityClass = $ns . '\\' . $class;
                    /** @var \Phalcon\Mvc\Model $entity */
                    $entity = new $entityClass();
                    $schema = $this->getSchemaName($entity->getSchema());
                    $entities[$entityClass] = array(
                        'schema' => $schema,
                        'table' => $entity->getSource(),
                        'columns' => $this->_describeTable(Entity::getTableNameWithSchema($schema, $entity->getSource()))
                    );
                }


            }
            $cache->save('MAPPER_' . md5($dir), serialize($entities));
        } else {
            $entities = unserialize($cache->get('MAPPER_' . md5($dir)));
        }

        return $entities;
    }

    /**
     * @param array $entities
     */
    private function _buildEntityMapper(array $entities)
    {
        foreach ($entities as $entityName => $options) {
            $this->tableToModel[$options['table']] = $entityName;

            $entityToTableMapper = new Entity();
            $entityToTableMapper->setName($entityName)->setTable($options['table'])->setSchema($options['schema'])->setColumns($options['columns']);
            $this->addEntity($entityToTableMapper);
        }
    }

    /**
     * @param String $table
     * @return array
     */
    private function _describeTable($table)
    {
        $sourceName = __NAMESPACE__ . "\\Source\\" . ucfirst($this->getDI()->get('db')->getType());
        /** @var \FhpPhalconGrid\Grid\Source\SourceInterface $source */
        $source = new $sourceName($table, $this->getDI());
        return $source->getColumns();
    }
}

?>