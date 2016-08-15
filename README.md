### Grid


#### limit, page, sort, filter, urls ,head
the `__` in all of the pattern is the constant `Column::SPLIT`

| Function       | pattern                | description                                                    |
|----------------|------------------------|----------------------------------------------------------------|
| limit          | limit__{int}           | `default:9999`                                                 |
| page           | page__{int}            | `default:1     |`                                              |
| head           | head__remove           | if this is set, there is no head information sent for the grid |
| sort or filter | sort__[field]__[value] |                                                                |

#### getAction()
| Function                             | Description                                                                                               |
|--------------------------------------|-----------------------------------------------------------------------------------------------------------|
| getType(mode)                        | gets the action view mode type                                                                            |
| getType(mode)->setVisible(bool)      | sets if the type should be shown                                                                          |
| getType(mode)->setForUrl(string)     | can set a different route `default:grid_action_route`                                                     |
| getType(mode)->setImage(string)      | set a image for the frontend      -change to class                                                                        |
| getType(mode)->setBehaviours(string) | atm only called in the delete mode.                                                                       |
| setRemove(bool)                      | removes the whole action                                                                                  |
| setCallback(callback)                | !!!not needed anymore i think                                                                             |
| setKeys(array)                       | Normally the keys and unique keys are used to create the link. but you are also free to use your own keys |

```php
 $grid->getAction()->setKeys(array('FhpPhalconAuth\Entity\User' => array('email')));
 $grid->getAction()->getType(Grid::DETAILS)->setVisible(false);
 $grid->getAction()->getType(Grid::DELETE)->addBehavior(new \Phalcon\Mvc\Model\Behavior\SoftDelete(array('field' => 'surname', 'value' => 1)));

 //....

```


#### Pagination
in the grid table mode the following array will return on a pagination
```php
$result = $this->getPagination()->setQuery($this->query, $this->getUrlParam(Grid::URL_PARAMS_PAGINATION))->getResult();
//$result
 array('items' => $this->result,
            'total_items' => $this->total,
            'total_pages' => $this->_getTotalPages(),
            'before' => $this->_before(),
            'current' => $this->_current(),
            'next' => $this->_next());
```


#### Column options
There are two ways to set the column settings. 

| Function             | Description                                                                            |
|----------------------|----------------------------------------------------------------------------------------|
| getColumn(name)      | For all columns which are in the Entity                                                |
| getColumnGroup(name) | For an relation to another Entity. On the result you can call `getColumn(name)` again. |

#### options
| Type          | Function                                         | Description                                                                                                                                                                                                                                             | Table | Details | Edit | Delete      |
|---------------|--------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------|---------|------|-------------|
| Column, Group | setRemove(bool,roles = array(),mode = null)      | You can remove a whole column or group for all or a specific view. The Column or Group will also be deleted in the database query. So you dont have any access to the value in any callback. If you still need the value, have a look at `setSqlRemove` | x     | x       | x    | -           |
| Column, Group | setSqlRemove(bool) `default: true`               | Here you can set if a removed Column should be still access able in callbacks. No view-mode is needed because its coupled to the isRemove function                                                                                                      | -     | -       | -    | -           |
| Column, Group | setPosition(int, mode = null)                    | A option to change the position of a group or column. The default order is like its positioned in the database. You can set different positions for the view-modes.                                                                                     | x     | x       | x    | -           |
| Column, Group | setHidden(bool,roles = array(),mode = null)      | A way to hide the column or group. (Idea: In the frontend you maybe dont wanna display all field but give the user the option to display it later on)                                                                                                   | x     | x       | x    | -           |
| Column, Group | setFilterable(bool) `default: true`              | Allow the filtering in the Frontend                                                                                                                                                                                                                     | x     | -       | -    | -           |
| Column, Group | setSortable(bool) `default: true`                | Allow the sorting in the Frontend                                                                                                                                                                                                                       | x     | -       | -    | -           |
| Column, Group | setName(string,mode = null)  `default: dba name` | Set a different name to the Group or Column                                                                                                                                                                                                             | x     | x       | x    | -           |
| Column, Group(?) | setCallback(callback, mode = null)               | Set a callback to the field before its displayed. Keep in mind the whole result set will be changed that can slow down the grid.                                                                                                                        | x     | x       | -    | -           |


#### In planning
| Type         | Function                                   | Description                                                                 | Table | Details | Edit | Delete |
|--------------|--------------------------------------------|-----------------------------------------------------------------------------|-------|---------|------|--------|
| Column       | setElement                                 | Allow to add a different element with different validators in the edit view | -     | -       | x    | -      |
| Group,Column | getPermission() allowEdit  `default:true`  | If the role have no access to that field, its read only in the edit view.   | -     | -       | x    | -      |
| Group        | getPermission() allowAdd    `default:true` | if the user is allowed to add new elements                                  | -     | -       | x    | -      |
| Group        | getPermission() allowRemove `default:true` | if the user is allowed to delete elements                                   | -     | -       | x    | -      |
 
 > You can also access the action column `Action::COLUMNALIAS` to rename it or do whatever you want.
 
```php
 $grid->getColumn(\FhpPhalconAuth\Grid\Action::COLUMNALIAS);
```


#### Events
| Name   | Eventname    | Params       |
|--------|--------------|--------------|
| Delete | beforeDelete | $this (grid) |
| Delete | afterDelete  | $this (grid) |


### TODO:
- create a sort function in js and check if its working with the backend
- create a js pagination and check if its working with the backend
- change setImage in Action to setClass
- change callback in Action to build a array for js
- write a small docu entry how the route is created
- all the in planing options (prio one permissions)
- a log module? check phalcons configs for that.
- go through the complete code and document it better and/or delete the unused parts

find . -name "*.php" -not -path "./tests*" | xargs wc -l