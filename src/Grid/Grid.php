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

use FhpPhalconHelper\JsonResponse;
use FhpPhalconGrid\Grid\Column\Callback;
use FhpPhalconGrid\Grid\Column\Group;
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

    //TODO delete
    private $debug = array();

    /**
     * @var
     */
    protected $_eventsManager = null;


    public function __construct(DiInterface $di)
    {
        $this->setDI($di);
        $this->debug['grid'] = $this->getDI()->get('profiler')->start('Grid-total', array(), 'Grid');
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
    public function setSource($source)
    {


        if (!is_a($source, '\Phalcon\Mvc\Model') AND !is_a($source, '\Phalcon\Mvc\Model\Query\Builder')) {
            throw new Exception('The source is no "Model" or "Builder Object"!');
        }

        //initialize actions
        $profiler = $this->getDI()->get('profiler')->start('getAction()', array(), 'Grid');
        $this->action = $this->getAction();
        $profiler->stop();

        //create entity to table mapper
        $profiler = $this->getDI()->get('profiler')->start('mapper', array(), 'Grid');
        $reflector = new \ReflectionClass(get_class($source));
        $fn = $reflector->getFileName();
        $this->mapper = new Mapper($this->getDI(), dirname($fn));

        $profiler->stop();

        //create query of a entity
        $profiler = $this->getDI()->get('profiler')->start('buildQuery', array(), 'Grid');
        if (is_a($source, '\Phalcon\Mvc\Model')) {
            $source = $this->_buildQuery($source);
        }
        $profiler->stop();

        $this->query = $source;


        $profiler = $this->getDI()->get('profiler')->start('createColumns()', array(), 'Grid');
        $this->_createColumns();
        $profiler->stop();


        //set grid mode
        $profiler = $this->getDI()->get('profiler')->start('setGridMode()', array(), 'Grid');
        $this->_setGridMode();
        $profiler->stop();

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
        $model = $modelName::findFirst($condition);

        //if entry exists
        if ($model) {
            //adding a columns to entity mapper
            $this->mapper->addColumnsToEntity($this->columns);

            //getting angular post/put data and convert it to an array
            $postdata = file_get_contents("php://input");
            $request = json_decode($postdata, true);

            //get all relation entities of that one
            foreach ($model->getModelsManager()->getRelations($modelName) as $relation) {
                $relationModelName = $relation->getReferencedModel();
                $relationAliasName = $relation->getOption('alias');

                $newEntityObjects = array();
                foreach ($request[Group::GROUP_PREFIX . $relationAliasName] as $row => $relationValue) {

                    //check if all fields are empty
                    $allFieldsAreEmpty = true;
                    foreach ($relationValue as $vl) {
                        if (!empty($vl)) {
                            $allFieldsAreEmpty = false;
                        }
                    }

                    if (!$allFieldsAreEmpty) {
                        //create new validation and add it to the group messenger
                        $validation = new Validation();
                        $relationModelColumns = $this->mapper->getColumnsOfEntity($relationModelName, 'noRemovedColumns');
                        foreach ($relationModelColumns as $tmpCol) {
                            $validators = $this->getColumnGroup($relationAliasName)->getColumn($tmpCol)->getValidators();
                            foreach ($validators as $validator) {
                                $validation->add($tmpCol, $validator);
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
                    $validation->add($tmpCol, $validator);
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
            } else {
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

        //parse the query
        $query = $this->query->getQuery()->parse();
        $this->mapper->addAliasFromQuery($query);
        //column positions
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

                $col = new Column();
                $col->setName($fieldName)
                    ->setType($type)
                    ->setDefault((isset($column['default']) ? $column['default'] : null))
                    ->setTable((isset($column['table']) ? $column['table'] : null))
                    ->setSchema((isset($column['schema']) ? $column['schema'] : null))
                    ->setTableAlias($tableAlias)
                    ->setField($field)
                    ->setAlias($alias);
                if (isset($column['type'])) {
                    $col->setFieldType($column['type'])->setValidator($validator::getValidator($column['type'], $column));
                }

                //add Callback to the action field and remove it from the details and edit view
                if ($fieldName == Action::COLUMNALIAS) {
                    $col->setCallback(array(new \FhpPhalconGrid\Grid\Action\Callback(), 'render'), Grid::TABLE);
                    $col->setRemove(true, array(), Grid::DETAILS);
                    $col->setRemove(true, array(), Grid::EDIT);
                }

                //Create the Group fields and modify the columns to correct them
                if (Text::startsWith($alias, Group::GROUP_PREFIX)) {
                    $relationAlias = Group::getRelationAlias($alias);


                    if (!isset($this->columns[$relationAlias])) {
                        $this->columns[$relationAlias] = new Group();
                        $this->columns[$relationAlias]->setPosition($position++);
                        $this->columns[$relationAlias]->setField(Group::getRelationAlias($alias, true));
                        $this->columns[$relationAlias]->setName(Group::getRelationAlias($alias, true));

                        //add a group callback for a better handling in angular
                        $this->columns[$relationAlias]->setCallback(array(new Callback(), 'groupRender'));
                    }
                    $col->setModelName($this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true)));

                    //TODO check if its added automatically and not manually - then it will not work
                    $groupColumn = $this->mapper->getEntity($this->mapper->getModelNameFromAlias(Group::getRelationAlias($alias, true)))->getColumn(str_replace(Group::getRelationAlias($alias) . Group::SPLIT, '', $fieldName));
                    if (isset($groupColumn['type'])) {
                        $col->setFieldType($groupColumn['type'])->setValidator($validator::getValidator($groupColumn['type'], $groupColumn));
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
            if (!is_callable($callback)) {
                throw new Exception('The callback is not callable!');
            }
            $this->result['items'] = call_user_func($callback, $field, $this);
        }
    }

    public function render()
    {
        $request = new Request();
        if (self::getMode() == Grid::EDIT && $request->getMethod() == 'PUT') {
            //SAVE
            $this->_save();
        } else {

            $profiler = $this->getDI()->get('profiler')->start('render()', array(), 'Grid');

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

            $this->_getHeadInformation();

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
                    $col[] = ($column->getTableAlias() ? '[' . $column->getTableAlias() . ']' . '.' : '') . $column->getField() . ($column->getType() == Column::TYPE_QUERY ? ' AS ' . $column->getAliasOrField() : '');
                    if ($column->getCallback()) {
                        $callbacks[$column->getAliasOrField()] = $column->getCallback();
                    }
                }

                //Create a subselect if its a group
                if (is_a($column, 'FhpPhalconGrid\Grid\Column\Group')) {
                    //sort columns
                    $relation = $this->modelsManager->getRelationByAlias($this->query->getFrom(), $column->getField());
                    $col[] = '(SELECT GROUP_CONCAT(CONCAT(' . implode(',"' . $column->getColumnSeparator() . '",', array_keys($column->getColumnsForSelect())) . '),"' . $column->getLineSeparator() . '") FROM ' . $relation->getReferencedModel() . ' WHERE ' . $this->mapper->getEntity($this->query->getFrom())->getPhqlName() . '.' . $relation->getFields() . ' = ' . $relation->getReferencedModel() . '.' . $relation->getReferencedFields() . ') as ' . $column->getAliasOrField();
                    $callbacks[$column->getAliasOrField()] = $column->getCallback();
                    //TODO Callbacks per field
                }
            }

            $this->query->columns(implode(',', $col));

            if (self::getMode() == Grid::DETAILS OR self::getMode() == Grid::EDIT) {
                $this->_createWhere();
                $result['items'] = $this->query->getQuery()->setUniqueRow(true)->execute();
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

            $profiler->stop();
            $this->debug['grid']->stop();

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
                    'filterable' => $column->isFilterable()
                );
            } else {
                $data[$column->getAliasOrField()] = array(
                    'displayName' => $column->getName(),
                    'sortable' => $column->isSortable(),
                    'hidden' => $column->isHidden(),
                    'filterable' => $column->isFilterable()
                );

                if ($this->getMode() == "edit") {
                    $data[$column->getAliasOrField()]['permission'] = $column->getPermission();
                }
            }


            if (!$column->isGroup()) {
                //TODO make better
                if ($this->getMode() == "edit") {
                    if ($group !== null) {
                        $data[$group]['fields'][$column->getAliasOrField()]['fieldType'] = $column->getFieldTypeAjax();
                        $data[$group]['fields'][$column->getAliasOrField()]['validators'] = $column->getValidatorsAjax();
                    } else {
                        $data[$column->getAliasOrField()]['fieldType'] = $column->getFieldTypeAjax();
                        $data[$column->getAliasOrField()]['validators'] = $column->getValidatorsAjax();
                    }
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

