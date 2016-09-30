<?php
/**
 * Grid
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid;

use FhpPhalconGrid\Grid\Column\Callback;
use FhpPhalconGrid\Grid\Column\Config;
use FhpPhalconGrid\Grid\Column\ConnectedTable;
use FhpPhalconGrid\Grid\Column\FieldType;
use FhpPhalconGrid\Grid\Column\Group;
use FhpPhalconGrid\Grid\Column\Permission;
use FhpPhalconHelper\JsonResponse;
use Phalcon\DiInterface;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\User\Component;
use Phalcon\Mvc\View;
use Phalcon\Paginator\Pager;
use Phalcon\Text;
use Phalcon\Validation;

class Grid extends Component implements EventsAwareInterface
{
    const MODE = 'gridmode';
    const TABLE = 'table';
    const EDIT = 'edit';
    const DETAILS = 'details';
    const DELETE = 'delete';
    const NEW = 'new';


    const EXPORT_PDF = 'pdf';
    const EXPORT_XLS = 'xls';

    const TEMPLATE_VIEW = 'view';
    const TEMPLATE_DETAILS = 'details';
    const TEMPLATE_EDIT = 'edit';

    const URL_PARAMS_PAGINATION = 'pagination';
    const URL_PARAMS_FILTER = 'filter';
    const URL_PARAMS_SORT = 'sort';
    const URL_PARAMS_HEADINFO = 'head';

    /**
     * Grid mode
     * @var null|String $mode
     */
    private static $mode = null;

    /**
     * All models described
     * @var Mapper $mapper
     */
    private $mapper;

    /**
     * All columns of the select or entity
     * @var array Column[]
     */
    private $columns = array();

    /**
     * Temporary saved builder
     * @var Model\Query\BuilderInterface $query
     */
    private $query;

    /**
     * @var null|Action
     */
    private $action = null;

    /**
     * Result after query
     * @var array $result
     */
    private $result;

    /**
     * @var null|Pagination
     */
    private $pagination = null;

    /**
     * @var null|array
     */
    private $gridUrlParams = null;

    /**
     * @var
     */
    protected $_eventsManager = null;

    public function __construct(DiInterface $di)
    {
        $this->setDI($di);
        if (!$this->getDI()->has('eventsManager')) {
            throw new Exception('Please initialize the eventsManager service!');
        }
    }

    public function setEventsManager(ManagerInterface $eventsManager)
    {
        $this->_eventsManager = $eventsManager;
    }

    public function getEventsManager()
    {
        if ($this->_eventsManager == null) {
            $this->_eventsManager = $this->getDI()->get('eventsManager');
        }
        return $this->_eventsManager;
    }

    /**
     * @return Action
     */
    public function getAction()
    {
        if ($this->action === null) {
            $this->action = new Action($this->getDI());
        }

        return $this->action;
    }

    /**
     * @param Model|\Phalcon\Mvc\Model\Query\Builder $source
     * @return $this
     * @throws Exception
     */
    public function setSource($source,$where = null)
    {


        if (!is_a($source, '\Phalcon\Mvc\Model') AND !is_a($source, '\Phalcon\Mvc\Model\Query\Builder')) {
            throw new Exception('The source is no "Model" or "Builder Object"!');
        }

        //initialize actions
        $this->action = $this->getAction();

        //create entity to table mapper
        $reflector = new \ReflectionClass(get_class($source));
        $fn = $reflector->getFileName();
        $this->mapper = new Mapper($this->getDI(), dirname($fn));

        //create query of a entity
        if (is_a($source, '\Phalcon\Mvc\Model')) {
            $source = $this->_buildQuery($source);
            if($where!=null){
                $source->where($where);
            }
        }
        $this->query = $source;

        $this->_createColumns();

        //set grid mode
        $this->_setGridMode();

        //DELETE - no render of the Grid needed
        $this->_delete();
        return $this;
    }

    /**
     * save function
     *
     * TODO: later - save when its a new entry
     * TODO: later - check if a value has changed
     * TODO: later - transactions only needed if there are relations of relations
     *
     * @throws Column\Exception
     * @throws Exception
     * @throws Mapper\Exception
     */
    protected function _save()
    {
        //Creating validation error message group
        $validError = new Validation\Message\Group();

        //fire before save event
        $this->getEventsManager()->fire("grid:beforeSave", $this);

        //TODO check with new entry?
        //select the entity with the given contitions
        $condition = $this->_createPhqlCondition();
        $modelName = $this->mapper->getModelNameFromAlias($this->query->getFrom());
        /** @var \Phalcon\Mvc\Model $model */
        if ($condition == GRID::NEW) {
            $model = new $modelName;
        } else {
            $model = $modelName::findFirst($condition);
        }

        //transaction start
        $this->db->begin();

        //if entry exists
        if ($model) {
            //adding a columns to entity mapper
            $this->mapper->addColumnsToEntity($this->columns);

            //getting angular post/put data and convert it to an array
            $postdata = file_get_contents("php://input");
            $request = json_decode($postdata, true);

            foreach ($request as $k => $req) {

                if(is_string($req)){
                    $request[$k] = trim($req);
                }

                if (is_array($req) AND array_key_exists('date', $req) AND array_key_exists('time', $req)) {
                    $date = new \DateTime($req['time']);
                    $request[$k] = $date->format('Y-m-d H:i:s');
                }
            }

            //get all relation entities of that one
            foreach ($model->getModelsManager()->getRelations($modelName) as $relation) {
                $relationModelName = $relation->getReferencedModel();
                $relationAliasName = $relation->getOption('alias');

                if ($this->_skipBelongsToAndHasOneRelationsOfFrom($relationModelName) || $this->getColumnGroup($relationAliasName)->isRemove()) {
                    continue;
                }

                //delete when group is shown
                if ($model->{$relationAliasName}) {
                    $model->{$relationAliasName}->delete();
                }

                $newEntityObjects = array();
                if (isset($request[Group::GROUP_PREFIX . $relationAliasName])) {
                    foreach ($request[Group::GROUP_PREFIX . $relationAliasName] as $row => $relationValue) {

                        //TODO Make it better to also add the validators - also the required should be connected to the group
                        if ($this->getColumnGroup($relationAliasName)->getConnectionTable()) {
                            $field = array_keys($this->getColumnGroup($relationAliasName)->getColumns());


                            $values = explode(',', $relationValue);
                            foreach ($values as $value) {
                                $tmpModel = new $relationModelName();
                                $tmpModel->assign(array($field[0] => $value), null, $field);
                                $newEntityObjects[] = $tmpModel;
                            }
                        } else {
                            //check if all fields are empty
                            $allFieldsAreEmpty = true;
                            $relationModelColumns = $this->mapper->getColumnsOfEntity($relationModelName, 'noRemovedColumns');

                            foreach ($relationValue as $k=>$vl) {

                                if(is_string($vl)){
                                    $relationValue[$k] = trim($vl);
                                }

                                if (!empty($vl) && $vl != "__autoincrement__") {
                                    $allFieldsAreEmpty = false;
                                }
                                //deletes autoincrement fields
                                if($vl == "__autoincrement__"){
                                    unset($relationValue[$k]);
                                    unset($relationModelColumns[array_search($k,$relationModelColumns)]);
                                }
                            }


                            if (!$allFieldsAreEmpty) {
                                //create new validation and add it to the group messenger
                                $validation = new Validation();
                                foreach ($relationModelColumns as $tmpCol) {
                                    $validators = $this->getColumnGroup($relationAliasName)->getColumn($tmpCol)->getValidators();
                                    if ($validators) {
                                        foreach ($validators as $validator) {
                                            $validation->add($tmpCol, $validator);
                                        }
                                    }
                                }
                                //validate with the given result
                                $errors = $validation->validate($relationValue);
                                //modify the error message field and code to assign the error better in angular later on
                                if ($errors->count() > 0) {
                                    foreach ($errors as $err) {
                                        $err->setCode($relationValue[$err->getField()]);
                                        $err->setField(Group::GROUP_PREFIX . $relationAliasName . '.' . $row . '.' . $err->getField());
                                        $validError->appendMessage($err);
                                    }
                                }


                                //create a model and assign the result
                                $tmpModel = new $relationModelName();

                                $tmpModel->assign($relationValue, null, $relationModelColumns);
                                $newEntityObjects[] = $tmpModel;
                            }
                        }

                    }
                }

                //assign the group-models to the main model
                if (count($newEntityObjects) > 0) {
                    $model->{$relationAliasName} = $newEntityObjects;
                }
            }


            //get all fields and associate it with the model name
            $modelColumns = $this->mapper->getColumnsOfEntity($model, 'noRemovedColumns');


            //add the validation
            $validation = new Validation();
            foreach ($modelColumns as $tmpCol) {
                $validators = $this->getColumn($tmpCol)->getValidators();
                foreach ($validators as $validator) {
                    if ($validator !== null) {
                        $validation->add($tmpCol, $validator);
                    }
                }
            }
            //modify the error message to assing it better in angular later on
            $errors = $validation->validate($request);
            if ($errors->count() > 0) {
                if ($errors->count() > 0) {
                    foreach ($errors as $err) {
                        $err->setCode($request[$err->getField()]);
                        $validError->appendMessage($err);
                    }
                }
            }


            //finally save the new entity
            if ($validError->count() == 0 && $model->save($request, $modelColumns) == false) {
                $this->response->setStatusCode(401);
                $errors = [];
                foreach ($model->getMessages() as $message) {
                    $errors[$message->getField()] = $message->getMessage();
                }

                $this->view->{JsonResponse::ERROR} = $errors;
            } elseif ($validError->count() != 0) {
                $this->response->setStatusCode(401);
                $errors = [];
                foreach ($validError as $message) {
                    $errors[$message->getField()] = $message->getMessage();
                }

                //Transaction rollback
                $this->db->rollback();
                $this->view->{JsonResponse::ERROR} = $errors;
            } else {
                //Transaction commit
                $this->db->commit();
                $this->view->{JsonResponse::INFO} = JsonResponse::SAVED;
            }
        } else {
            throw new Exception("No entry was found!");
        }

        $this->getEventsManager()->fire("grid:afterSave", $this);
    }

    protected function _delete()
    {
        if (self::getMode() == Grid::DELETE) {
            $this->getEventsManager()->fire("grid:beforeDelete", $this);

            $condition = $this->_createPhqlCondition();
            $modelName = $this->mapper->getModelNameFromAlias($this->query->getFrom());
            /** @var \Phalcon\Mvc\Model $model */

            //a id must be given
            if (count($this->getAction()->getLinkParams()) == 1) {
                exit;
            }

            $model = $modelName::findFirst($condition);
            if ($model) {
                $behaviors = $this->getAction()->getType(Grid::DELETE)->getBehaviors();
                if ($behaviors) {
                    foreach ($behaviors as $behavior) {
                        $model->addBehavior($behavior);
                    }
                }
                $model->delete();
            }

            $this->getEventsManager()->fire("grid:afterDelete", $this);
            exit;
        }
    }

    /**
     * set the grid mode by url
     */
    protected function _setGridMode()
    {

        switch ($this->dispatcher->getParam(self::MODE)) {
            case "edit":
                self::$mode = self::EDIT;

                if (in_array(Grid::EDIT, $this->dispatcher->getParams()) && in_array(Grid::NEW, $this->dispatcher->getParams())) {
                    self::$mode = self::NEW;
                }

                $this->view->pick('grid/' . self::EDIT);
                break;
            case "details":
                self::$mode = self::DETAILS;
                $this->view->pick('grid/' . self::DETAILS);
                break;
            case "delete":
                self::$mode = self::DELETE;
                $this->view->pick('grid/' . self::DELETE);
                break;
            default:
                self::$mode = self::TABLE;
                $this->_convertGridTableParams();
                $this->view->pick('grid/' . self::TABLE);
                break;
        }
    }

    private function _setFieldValues($column)
    {
        if ($column->getConnectionTable() !== false) {
            $connectionTable = $column->getConnectionTable();
            $column->setFieldValue($connectionTable->getFieldValues());
        }
    }

    private function _columnHasOneRelation($entity, $col, $col2)
    {

        $col = Group::getColumnName($col);
        $relations = $this->mapper->getEntity($entity)->getRelations();


        if (count($relations) > 0) {
            foreach ($relations as $relation) {
                if ($relation->getType() == Model\Relation::HAS_ONE AND $relation->getFields() == $col) {
                    $col2->setFieldType('enum');
                    if ($this->query->getFrom() == $entity) {
                        $col2->setCallback(array(new Callback(), 'hasOneRender'), Grid::TABLE);
                    }
                    $connection = new ConnectedTable();
                    $connection->setSourceTable($relation->getReferencedModel())->setSourceField($relation->getReferencedFields());
                    $col2->setConnectionTable($connection);
                }
            }
        }
    }

    private function _skipBelongsToAndHasOneRelationsOfFrom($entityName)
    {
        $fromRelations = $this->mapper->getEntity($this->query->getFrom())->getRelations();
        $rv = false;

        foreach ($fromRelations as $relation) {
            if ($relation->getReferencedModel() == $entityName) {
                if (in_array($relation->getType(), array(Model\Relation::BELONGS_TO, Model\Relation::HAS_ONE))) {
                    $rv = true;
                } else {
                    return false;
                }
            }
        }

        return $rv;
    }

    /**
     * create all the columns from the builder query
     * @throws Exception
     */
    private function _createColumns()
    {
        /*
         * creates the action column if its activated and
         * sets the keys if they aren´t set manually yet
         */
        if (!$this->getAction()->isRemoved()) {
            if (count($this->getAction()->getKeys()) == 0) {
                $keys = $this->mapper->getPrimaryAndUniqueKey($this->query->getFrom());
                $this->getAction()->setKeys($keys);
            }
            $this->query = $this->getAction()->createActionColumn($this->query);
        }

        //var_dump('COLUMNS_'.md5($this->query->getFrom()));

        //parse the query
        $query = $this->query->getQuery()->parse();
        $this->mapper->addAliasFromQuery($query);
        //column positions

        $cache = $this->getDI()->get('cache');
        if ($cache->get('COLUMNS_' . md5($this->query->getFrom())) === null) {

            $position = 0;
            $colPosition = 0;
            $columns = $this->query->getColumns();
            /** @var \FhpPhalconGrid\Grid\Source\SourceInterface $validator */
            $validator = __NAMESPACE__ . "\\Source\\" . ucfirst($this->getDI()->get('db')->getType());


            foreach ($query['columns'] as $name => $sqlColumns) {


                $buildColumns = array();
                $alias = null;
                $field = null;
                $tableAlias = null;
                $type = Column::TYPE_QUERY;

                switch ($sqlColumns['type']) {

                    //for "select *"
                    case "object":
                        //needed to map the $query->getColumns() to this array
                        foreach ($columns as $key => $col) {
                            if (stristr($col, '*')) {
                                unset($columns[$key]);
                            }
                        }
                        $columns = array_values($columns);
                        $colPosition = $colPosition - 1;

                        $tableAlias = $sqlColumns['column'];
                        $buildColumns = $this->mapper->getEntity($sqlColumns['model'])->getColumns();

                        break;
                    //for single columns
                    case "scalar":
                        if ($sqlColumns['column']['type'] == "qualified") {
                            $tableAlias = $sqlColumns['column']['domain'];
                            $name = $sqlColumns['column']['name'];
                        }

                        //functions
                        if ($sqlColumns['column']['type'] == "functionCall") {
                            $field = (is_string($columns) ? $columns : $columns[$colPosition]);
                            $type = Column::TYPE_FUNCTION;
                        }

                        //sub selects
                        if ($sqlColumns['column']['type'] == "parentheses") {
                            $type = Column::TYPE_SUBQUERY;
                            $field = $columns[$colPosition];
                        }

                        //get the column description
                        $buildColumns[$name] = array();
                        if ($type === Column::TYPE_QUERY) {
                            $buildColumns[$name] = $this->mapper->getEntity($this->mapper->getModelNameFromAlias($tableAlias))->getColumn($name);
                        }

                        //set alias if its not the same as the column name
                        if (isset($sqlColumns['balias']) AND $sqlColumns['balias'] != $field) {
                            $alias = $sqlColumns['balias'];
                        }

                        break;
                }


                //build the columns
                foreach ($buildColumns as $fieldName => $column) {

                    //TODO - delete Group
                    $relationAlias = Group::getRelationAlias($fieldName, 1);
                    $keys = $this->mapper->getKeysToHideFromModelName($this->mapper->getModelNameFromAlias(($relationAlias) ? $relationAlias : $this->query->getFrom()), 1);


                    //TODO create a function for the relations
                    //TODO create a function to check if its a Group

                    $col = new Column();
                    $col->setName($fieldName)
                        ->setType($type)
                        ->setDefault((isset($column['default']) ? $column['default'] : null))
                        ->setTable((isset($column['table']) ? $column['table'] : null))
                        ->setSchema((isset($column['schema']) ? $column['schema'] : null))
                        ->setTableAlias($tableAlias)
                        ->setField($field)
                        ->setAlias($alias);


                    //hiden id and related fields
                    if (in_array(($relationAlias) ? Group::getColumnName($fieldName) : $fieldName, $keys)) {
                        $col->setRemove(true);
                        $col->setRemove(false, array(), Grid::EDIT);
                        $col->setHidden(true, array(), Grid::EDIT);
                        $col->setRemove(true, array(), Grid::NEW);
                    }


                    if (isset($column['type']) && ($column['type'] == 'enum' || $column['type'] == 'set')) {
                        $col->setFieldValue($column['length']);
                    }

                    if (isset($column['type'])) {
                        $col->setFieldType($column['type'])->setValidator($validator::getValidator($column['type'], $column));
                        if ($column['type'] == "datetime" || $column['type'] == "timestamp") {
                            $col->setCallback(array(new Callback(), 'datetime'), Grid::EDIT);
                            $col->setCallback(array(new Callback(), 'datetime'), Grid::NEW);

                        }
                    }


                    $this->_columnHasOneRelation($this->mapper->getModelNameFromAlias(($relationAlias) ? $relationAlias : $this->query->getFrom()), $fieldName, $col);

                    //add Callback to the action field and remove it from the details and edit view
                    if ($fieldName == Action::COLUMNALIAS) {
                        $col->setCallback(array(new \FhpPhalconGrid\Grid\Action\Callback(), 'render'), Grid::TABLE);
                        $col->setRemove(true, array(), Grid::DETAILS);
                        $col->setRemove(true, array(), Grid::EDIT);
                        $col->setRemove(true, array(), Grid::NEW);
                        $col->setSortable(false);
                        $col->setFilterable(false);
                    }

                    //Create the Group fields and modify the columns to correct them
                    if (Text::startsWith($alias, Group::GROUP_PREFIX)) {
                        $relationAlias = Group::getRelationAlias($alias);

                        //TODO put it into own
                        $test = $this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true));

                        //use it when its a belongto table
                        if ($this->_skipBelongsToAndHasOneRelationsOfFrom($test)) {
                            $col->setSqlRemove(true);
                        }

                        if (!isset($this->columns[$relationAlias])) {

                            $relations = $this->modelsManager->getBelongsTo(new $test);

                            $this->columns[$relationAlias] = new Group();
                            $this->columns[$relationAlias]->setPosition($position++);
                            $this->columns[$relationAlias]->setField(Group::getRelationAlias($alias, true));
                            $this->columns[$relationAlias]->setName(Group::getRelationAlias($alias, true));


                            //TODO put it into own
                            $test = $this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true));
                            $relations = $this->modelsManager->getBelongsTo(new $test);


                            $rel = $this->mapper->getEntity($this->query->getFrom())->getRelations();

                            if (count($relations) == 2) {

                                $hasFromTable = false;
                                $relatedArr = array();
                                foreach ($relations as $relation) {

                                    if ($relation->getReferencedModel() == $this->query->getFrom() &&
                                        $relation->getFields() == $rel[$test.Group::getRelationAlias($alias, true)]->getReferencedFields()
                                    ) { //and from table related field
                                        $hasFromTable = true;
                                    } else {
                                        $relatedArr = new ConnectedTable();
                                        $relatedArr->setAlias($relation->getOptions()['alias'])
                                            ->setConnectedFromField($rel[$test.Group::getRelationAlias($alias, true)]->getReferencedFields())
                                            ->setConnectedField($rel[$test.Group::getRelationAlias($alias, true)]->getFields())
                                            ->setConnectedTable($test)
                                            ->setSourceTable($relation->getReferencedModel())
                                            ->setConnectedToField($relation->getFields())
                                            ->setSourceField($relation->getReferencedFields());
                                    }
                                }
                                //related table
                                if ($hasFromTable == true && count($relatedArr) > 0) {
                                    $this->columns[$relationAlias]->setConnectionTable($relatedArr);
                                }
                            }
                            //add a group callback for a better handling in angular
                            $this->columns[$relationAlias]->setCallback(array(new Callback(), 'groupRender'));
                        }
                        $cTable = $this->columns[$relationAlias]->getConnectionTable();


                        $col->setModelName($this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true)));

                        //TODO check if its added automatically and not manually - then it will not work
                        $groupColumn = $this->mapper->getEntity($this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true)))->getColumn(str_replace(Group::getRelationAlias($alias) . Group::SPLIT, '', $fieldName));
                        if (isset($groupColumn['type'])) {

                            if ($col->getFieldType() == null) {
                                $col->setFieldType($groupColumn['type'])->setValidator($validator::getValidator($groupColumn['type'], $groupColumn));
                            }

                            if ($groupColumn['type'] == "datetime" || $groupColumn['type'] == "timestamp") {
                                $col->setCallback(array(new Callback(), 'datetime'), Grid::EDIT);
                                $col->setCallback(array(new Callback(), 'datetime'), Grid::NEW);

                            }

                            if ($groupColumn['type'] == 'enum' || $groupColumn['type'] == 'set') {
                                $col->setFieldValue($groupColumn['length']);
                            }
                        }

                        //TODO put it to a own method
                        if ($cTable) {
                            if ($relationAlias . '__' . $cTable->getConnectedToField() != $fieldName) {
                                continue;
                            } else {
                                $col->setRemove(false);
                                $col->setFieldType('set');
                                $col->setValidator(null);

                                $permission = new Permission();
                                $config = new Config(false, array());
                                $permission->allowAdd($config)->allowRemove($config);
                                $this->columns[$relationAlias]->setPermission($permission);
                            }
                        }

                        $this->columns[$relationAlias]->addColumn($col);
                    } else {
                        $col->setPosition($position++)
                            ->setModelName($this->mapper->getModelNameFromTable($col->getTable()))
                            ->build();
                        $this->columns[$col->getAliasOrField()] = $col;
                    }
                }
                $colPosition++;
            }

            //$cache->save('COLUMNS_' . md5($this->query->getFrom()), serialize($this->columns));
        } else {
            $this->columns = unserialize($cache->get('COLUMNS_' . md5($this->query->getFrom())));
        }

    }

    /**
     * Is needed for the callback
     * @return array
     */
    public function getResult()
    {
        return $this->result['items'];
    }

    protected function columnCallbacks($callbacks)
    {

        foreach ($callbacks as $field => $callback) {
            if ($callback === false) {
                continue;
            }

            //check if group
            if (Text::startsWith($field, Group::GROUP_PREFIX)) {
                foreach ($callback as $groupColumnName => $groupColumnCallback) {
                    if ($groupColumnName == "groupCallback") {
                        $this->result['items'] = call_user_func($groupColumnCallback, $field, null, $this);
                    } else {
                        $this->result['items'] = call_user_func($groupColumnCallback, $groupColumnName, $field, $this);
                    }
                }
            } else {
                if (!is_callable($callback)) {
                    throw new Exception('The callback is not callable!');
                }
                $this->result['items'] = call_user_func($callback, $field, null, $this);
            }
        }
    }

    public function render()
    {


        $request = new Request();
        if ((self::getMode() == Grid::EDIT || self::getMode() == Grid::NEW) && $request->getMethod() == 'PUT') {
            //SAVE
            $this->_save();
        } else {

            /*
             * removes the action column if all action types are invisible
             * if not, there is a check if the user added keys manually
             */
            if ($this->getAction()->isRemoved()) {
                $this->removeColumn(Action::COLUMNALIAS);
            } elseif ($this->action->hasChanged()) {
                $this->getColumn(Action::COLUMNALIAS)->setField($this->action->createActionColumn());
            }

            //sort columns
            uasort($this->columns, 'FhpPhalconGrid\Grid\Grid::_sortingColumns');

            $col = array();
            $callbacks = array();
            /** @var Column $column */

            foreach ($this->columns as $column) {

                //skip the column if its really removed
                if ($column->isSqlRemove()) {
                    continue;
                }

                //normal column
                if (is_a($column, 'FhpPhalconGrid\Grid\Column')) {
                    $column->build();
                    if($column->getPhql()){
                        $col[] = $column->getPhql();

                    }else{
                        $col[] = ($column->getTableAlias() ? '[' . $column->getTableAlias() . ']' . '.' : '') . $column->getField() . ($column->getType() == Column::TYPE_QUERY ? ' AS ' . $column->getAliasOrField() : '');
                    }
                    if ($column->getCallback()) {
                        $callbacks[$column->getAliasOrField()] = $column->getCallback();
                    }
                    $this->_setFieldValues($column);
                }

                //Create a subselect if its a group
                if (is_a($column, 'FhpPhalconGrid\Grid\Column\Group')) {
                    //Just a connection table relation
                    if ($column->getConnectionTable() !== false) {
                        $cTable = $column->getConnectionTable();
                        $cTableCol = $column->getColumn($cTable->getConnectedToField());
                        //setting name of the Group to column
                        $cTableCol->setName($column->getName());
                        $col[] = '(SELECT GROUP_CONCAT(' . $this->mapper->getEntity($cTable->getConnectedTable())->getPhqlName() . '.' . $cTable->getConnectedToField() . ') FROM ' . $cTable->getConnectedTable() . ' WHERE ' . $this->mapper->getEntity($this->query->getFrom())->getPhqlName() . '.' . $cTable->getConnectedField() . ' = ' . $cTable->getConnectedTable() . '.' . $cTable->getConnectedFromField() . ') as ' . $column->getAliasOrField();
                        $callbacks[$column->getAliasOrField()]['groupCallback'] = $column->getCallback();
                        $cTableCol->setFieldValue($cTable->getFieldValues());

                        if ($cTableCol->getCallback()) {
                            $callbacks[$column->getAliasOrField()][$cTableCol->getAliasOrField()] = $cTableCol->getCallback();
                        }
                    } else {
                        //IF all the columns are removed or its just a connection entity
                        // if(count($column->getColumnsForSelect())>0){
                        $relation = $this->modelsManager->getRelationByAlias($this->query->getFrom(), $column->getField());
                        $col[] = '(SELECT GROUP_CONCAT(CONCAT(IFNULL(' . implode(',""),"' . $column->getColumnSeparator() . '",IFNULL(', array_keys($column->getColumnsForSelect())) . ',"")),"' . $column->getLineSeparator() . '") FROM ' . $relation->getReferencedModel() . ' WHERE ' . $this->mapper->getEntity($this->query->getFrom())->getPhqlName() . '.' . $relation->getFields() . ' = ' . $relation->getReferencedModel() . '.' . $relation->getReferencedFields() . ') as ' . $column->getAliasOrField();
                        $callbacks[$column->getAliasOrField()]['groupCallback'] = $column->getCallback();
                        foreach ($column->getColumns() as $tkey => $tCol) {
                            $this->_setFieldValues($tCol);
                            if ($tCol->getCallback()) {
                                $callbacks[$column->getAliasOrField()][$tCol->getAliasOrField()] = $tCol->getCallback();
                            }
                        }
                    }
                }
            }
            $this->query->columns(implode(',', $col));

            $this->_getHeadInformation();

            if (self::getMode() == Grid::DETAILS OR self::getMode() == Grid::EDIT OR self::getMode() == Grid::NEW) {
                $this->_createWhere();
                $result['items'] = $this->query->getQuery()->setUniqueRow(true)->execute();


                //manipulate value
                if(self::getMode() == Grid::EDIT OR self::getMode() == Grid::NEW){
                    foreach($this->columns as $ckey => $column){
                        if(method_exists($column,'getValue') && !empty($column->getValue())){
                            if(!is_array( $result['items'] )){
                                $result['items']  = (array)  $result['items'] ;
                            }
                            $result['items'][$ckey] = $column->getValue();
                        }
                    }
                }

            } else {
                //add sorting and filter function
                $this->setOrderBy();
                $this->setFilter();
                $result = $this->getPagination()->setQuery($this->query, $this->getUrlParam(Grid::URL_PARAMS_PAGINATION))->getResult();
            }
            $this->result = $result;


            $this->columnCallbacks($callbacks);

            //$this->getEventsManager()->fire("grid:afterQuery", $this, $result);

            $this->view->data = $this->result;
        }
    }

    protected function setFilter()
    {

        //TODO combine that one with _createWhere
        $db = $this->getDI()->get('db');
        $sortCriteria = $this->getUrlParam('filter');

        $where = '';
        $whereBinds = [];
        $having = '';

        foreach ($sortCriteria as $field => $value) {
            $col = $this->getColumn($field);
            if ($col->getType() == "query") {
                if ($where != "") {
                    $where .= ' AND ';
                }
                $where .= $field . ' LIKE :' . $field . ':';
                $whereBinds[$field] = '%' . $value . '%';
            } else {
                if ($having != "") {
                    $having .= ' AND ';
                }
                $having .= $field . ' LIKE ' . $db->escapeString('%' . $value . '%');
            }
        }

        if ($where != '') {
            $this->query->where($where, $whereBinds);
        }
        if ($having != '') {
            $this->query->having($having);
        }

    }


    /**
     * adds a order by to the query
     * @throws Exception
     */
    protected function setOrderBy()
    {
        $sortCriteria = $this->getUrlParam('sort');
        $orderBy = '';

        foreach ($sortCriteria as $field => $direction) {
            //security check if the field exists
            $this->getColumn($field);
            $direction = strtoupper($direction);
            if (!in_array($direction, array('ASC', 'DESC'))) {
                throw new Exception('The value "' . $direction . '" is not a valid sorting direction!');
            }

            if ($orderBy != '') {
                $orderBy .= ',';
            }
            $orderBy .= $field . ' ' . $direction;
        }
        if ($orderBy !== '') {
            $this->query->orderBy($orderBy);
        }
    }


    //TODO needed for the pagination maybe make it static or make this method even in th Pagination
    public function getUrlParam($type)
    {
        if (isset($this->gridUrlParams[$type])) {
            return $this->gridUrlParams[$type];
        }
        return array();
    }

    /**
     * Grid mode = table
     * convert url params to an array like
     * $url[pagination]['pager']
     *                 ['limit']
     *     [filter]['columnname'] = value
     *     [sort]['columnname']= value
     */
    protected function _convertGridTableParams()
    {
        $params = $this->dispatcher->getParams();

        $gridParams = array();
        foreach ($params as $param) {
            foreach (array('limit', 'page', self::URL_PARAMS_FILTER, self::URL_PARAMS_SORT, self::URL_PARAMS_HEADINFO) as $type) {
                if (strstr($param, $type . Column::SPLIT)) {
                    if ($type == "limit" or $type == "page") {
                        $gridParams[self::URL_PARAMS_PAGINATION][$type] = str_replace($type . Column::SPLIT, '', $param);
                    }
                    if ($type == self::URL_PARAMS_HEADINFO) {
                        $gridParams[$type] = str_replace($type . Column::SPLIT, '', $param);;
                    }
                    if ($type == self::URL_PARAMS_FILTER or $type == self::URL_PARAMS_SORT) {
                        $field = substr($param, strlen($type . Column::SPLIT), strrpos($param, Column::SPLIT) - strlen($type . Column::SPLIT));
                        $value = substr($param, strrpos($param, Column::SPLIT) + strlen(Column::SPLIT));
                        $gridParams[$type][$field] = $value;
                    }
                }
            }
        }
        $this->gridUrlParams = $gridParams;
    }


    //TODO combine this and the create Where function
    protected function _createPhqlCondition()
    {
        $i = 0;
        $condition = array('conditions' => '', 'bind' => array());
        foreach ($this->getAction()->getLinkParams() as $k => $params) {
            if ($k == Grid::MODE) {
                continue;
            }
            $and = '';
            if (!empty($condition['conditions'])) {
                $and = $condition['conditions'] . ' AND ';
            }
            $condition['conditions'] = $and . $k . ' = :' . $i . ':';
            $condition['bind'][$i] = $params;
            $i++;

            if ($params == GRID::NEW) {
                return $params;
            }
        }
        return $condition;
    }

    //TODO combine this and the create phql condition function
    protected function _createWhere()
    {
//TODO combine it with setFilter
        if ($this->getAction()->getLinkParams()) {

            //TODO Check if where or Having
            //TODO check Bind type
            $i = 0;
            foreach ($this->getAction()->getLinkParams() as $k => $param) {
                if ($k == self::MODE) {
                    continue;
                }
                if ($this->query->getWhere() === null) {
                    $this->query->where($k . ' = :' . $i . ':', array($i => $param));
                } else {
                    $this->query->andWhere($k . ' = :' . $i . ':', array($i => $param));
                }

                $i++;
            }

        }

    }

    /**
     * @param $column
     * @return Column
     * @throws Exception
     */
    public function getColumn($column)
    {
        if (!isset($this->columns[$column])) {
            throw new Exception('The column "' . $column . '" is unknown!');
        }

        return $this->columns[$column];
    }

    /**
     * @param $column
     * @return bool
     */
    public function columnExists($column)
    {
        return isset($this->columns[$column]);
    }

    /**
     * @param $group
     * @return Group
     * @throws Exception
     */
    public function getColumnGroup($group)
    {
        if (!isset($this->columns[Group::GROUP_PREFIX . $group])) {
            throw new Exception('The group "' . $group . '" is unknown!');
        }
        return $this->columns[Group::GROUP_PREFIX . $group];
    }

    protected function removeColumn($name)
    {
        if (isset($this->columns[$name])) {
            unset($this->columns[$name]);
        }
    }

    /**
     * @param $data
     * @param $columns
     * @param null $group
     * @return array
     */
    private function _createHeadInformationArray($data, $columns, $group = null)
    {

        foreach ($columns as $table => $column) {

            if ($column->isRemove()) {
                continue;
            }

            //TODO make better
            if ($group !== null) {
                $data[$group]['fields'][$column->getAliasOrField()] = array(
                    'displayName' => $column->getName(),
                    'sortable' => $column->isSortable(),
                    'hidden' => $column->isHidden(),
                    'filterable' => $column->isFilterable(),

                );
            } else {

                $data[$column->getAliasOrField()] = array(
                    'displayName' => $column->getName(),
                    'sortable' => $column->isSortable(),
                    'hidden' => $column->isHidden(),
                    'filterable' => $column->isFilterable()
                );

                if ($column->isGroup()) {
                    $data[$column->getAliasOrField()]['connectionTable'] = ($column->getConnectionTable() ? true : false);
                }


                if ($this->getMode() == Grid::EDIT || $this->getMode() == Grid::NEW) {
                    $data[$column->getAliasOrField()]['permission'] = $column->getPermission();
                }
            }

            if (!$column->isGroup()) {
                //TODO make better - fieldType only in grid till filter is there
                if ($group !== null) {
                    $data[$group]['fields'][$column->getAliasOrField()]['fieldDefaultValue'] = $column->getFieldDefaultValue();
                    $data[$group]['fields'][$column->getAliasOrField()]['fieldType'] = $column->getFieldTypeAjax();
                    $data[$group]['fields'][$column->getAliasOrField()]['fieldValue'] = $column->getFieldValue();
                    $data[$group]['fields'][$column->getAliasOrField()]['validators'] = $column->getValidatorsAjax();
                } else {
                    $data[$column->getAliasOrField()]['fieldType'] = $column->getFieldTypeAjax();
                    $data[$column->getAliasOrField()]['fieldValue'] = $column->getFieldValue();
                    $data[$column->getAliasOrField()]['fieldDefaultValue'] = $column->getFieldDefaultValue();

                    $data[$column->getAliasOrField()]['validators'] = $column->getValidatorsAjax();
                }
            } else {


                $data = $this->_createHeadInformationArray($data, $column->getColumns(), $table);
            }
        }


        return $data;
    }

    /**
     * @return void
     */
    private function _getHeadInformation()
    {
        if ($this->getUrlParam('head') == "remove") {
            return false;
        }

        $view = array();
        $data = array();

        $data = $this->_createHeadInformationArray($data, $this->columns);

        $view['fields'] = $data;
        //links in a angular ui.route style
        $view['action'] = $this->getAction()->getLinkPattern(true);

        $this->view->head = $view;
    }


    /**
     * @param Model $entity
     * @return Model\Query\BuilderInterface
     * @throws Exception
     * @throws Mapper\Exception
     */
    private function _buildQuery(Model $entity)
    {
        $entityClass = get_class($entity);
        /** @var \Phalcon\Mvc\Model\ManagerInterface $modelManager */
        $modelManager = $entity->getModelsManager();
        /** @var \Phalcon\Mvc\Model\RelationInterface[] $relations */
        $relations = $modelManager->getRelations($entityClass);
        /** @var \Phalcon\Mvc\Model\Query\BuilderInterface $builder */
        $builder = $modelManager->createBuilder();

        //add the from table
        $builder->from($entityClass);
        $columns[] = '[' . $entityClass . '].*';

        /** @var \Phalcon\Mvc\Model\Relation $relation */
        foreach ($relations as $relation) {
            if ($relation->getOption('alias') === null) {
                throw new Exception('Please set an alias for the relation "' . $relation->getReferencedModel() . '"!');
            }
            //fetch all columns from this model, a temporary subselcet is created
            $this->mapper->addAlias($relation->getOption('alias'), $relation->getReferencedModel());
            $entity = $this->mapper->getEntity($relation->getReferencedModel());
            foreach ($entity->getColumns() as $name => $column) {
                $columns[] = '(SELECT GROUP_CONCAT(' . $entity->getPhqlName() . '.' . $name . ') FROM ' . $entity->getPhqlName() . ' WHERE ' . $this->mapper->getEntity($entityClass)->getPhqlName() . '.' . $relation->getFields() . ' = ' . $entity->getPhqlName() . '.' . $relation->getReferencedFields() . ') as ' . Group::getAliasName($relation->getOption('alias'), $name);
            }
        }

        //adding the columns
        $builder->columns($columns);

        return $builder;
    }

    /**
     * @return Pagination
     */
    public function getPagination()
    {
        if ($this->pagination == null) {
            $this->pagination = new Pagination();
        }
        return $this->pagination;
    }

    /**
     * @return null|String
     */
    public static function getMode()
    {
        return self::$mode;
    }

    /**
     * @param Column $a
     * @param Column $b
     * @return int
     */
    public static function _sortingColumns($a, $b)
    {
        if ($a->getPosition() == $b->getPosition()) {
            return 0;
        }
        return ($a->getPosition() < $b->getPosition()) ? -1 : 1;
    }
}

