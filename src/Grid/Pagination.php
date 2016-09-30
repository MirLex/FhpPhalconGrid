<?php
/**
 * Pagination
 *
 * @author      Patrick Ascher <pat@fullhouse-productions.com>
 * @copyright   Patrick Ascher
 * @version     0.1
 * @package     FhpPhalconGrid
 */

namespace FhpPhalconGrid\Grid;

use Phalcon\Mvc\Model\Query\BuilderInterface;

class Pagination
{
    var $params;
    var $limit = 0;
    /**
     * @var BuilderInterface
     */
    var $orig;
    var $query;
    var $offset = null;
    var $page;
    var $result;
    var $total;

    /**
     * gets the result and the final pagination array
     * thows an error if the limit and offset are not matching
     * @return array
     * @throws Exception
     */
    public function getResult()
    {

        $this->result = $this->query->getQuery()->execute()->toArray();
        //SQL_CALC_FOUND_ROWS was not used because count is faster
        $total = $this->orig->columns('COUNT(id) as total,' . $this->query->getColumns())->getQuery()->execute();

        if(count($total)>0){
            $this->total = (int)$total[0]['total'];
        }else{
            $this->total = 0;
        }
        
        

        return array('items' => $this->result,
            'total_items' => $this->total,
            'total_pages' => $this->_getTotalPages(),
            'before' => $this->_before(),
            'current' => $this->_current(),
            'next' => $this->_next());
    }

    /**
     * @param BuilderInterface $query
     * @param boolean|array $paginationParams
     * @return Pagination
     */
    public function setQuery(BuilderInterface $query, $paginationParams)
    {
        $this->params = $paginationParams;
        $this->query = $query;
        //had to be cloned because there is no remove limit function in phalcon yet
        $this->orig = clone $query;

        $this->_setLimitToQuery();
        return $this;
    }


    /**
     * give back the prev page number, if there is none, false will return
     * @return bool|int
     */
    protected function _before()
    {
        if ($this->_current() > $this->_getTotalPages()) {
            return $this->_getTotalPages();
        }

        if ($this->_current() != 1) {
            return $this->_current() - 1;
        }
        return false;
    }

    /**
     * returns the current page number
     * started with 1 not 0
     * @return int
     */
    protected function _current()
    {
        return (int)$this->page + 1;
    }

    /**
     * give back the next page number if there is one, false if not
     * @return bool|int
     */
    protected function _next()
    {
        if ($this->_current() < $this->_getTotalPages()) {
            return $this->_current() + 1;
        }
        return false;
    }

    /**
     * returns the total pages
     * @return int
     */
    protected function _getTotalPages()
    {
        if(count($this->result)==0){
            return 1;
        }


        return ceil(($this->total / $this->limit));
    }

    /**
     * adds the limit() with the right params to
     * the query
     */
    protected function _setLimitToQuery()
    {
        $this->_setPaginationLimit();
        $this->_setPaginationOffset();

        if ($this->limit !== 0) {
            $this->query->limit($this->limit, $this->offset);
        }
    }

    /**
     * sets the variable limit
     * @return Int
     */
    protected function _setPaginationLimit()
    {
        if ($this->params AND isset($this->params['limit'])) {
            $this->limit = $this->params['limit'];
        }

        return $this->limit;
    }

    /**
     * sets the variable offset and page
     * @return Int
     */
    protected function _setPaginationOffset()
    {
        if ($this->params AND isset($this->params['page'])) {
            $this->page = ($this->params['page'] > 0) ? $this->params['page'] - 1 : 0;
            $this->offset = $this->limit * $this->page;
        }

        return $this->offset;
    }
}