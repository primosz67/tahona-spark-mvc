<?php
/**
 *
 *
 * Date: 17.02.16
 * Time: 00:11
 */

namespace Spark\Core\Filter;


use Spark\Http\Request;
use Spark\Utils\Objects;

class FilterChain {

    /**
     * @var HttpFilter
     */
    private $filter;
    /**
     * @var \Iterator
     */
    private $filters;

    public function __construct($filter = null, \Iterator $filters) {
        $this->filters = $filters;
        $this->filter = $filter;

    }

    public function doFilter(Request $request) {
        if (Objects::isNotNull($this->filter)) {
            $this->filters->next();
            $filter = $this->filters->current();
            $nextFilterChain = new FilterChain($filter, $this->filters);
            $this->invokeCurrentFilter($request, $nextFilterChain);
        }
    }

    /**
     * @param Request $request
     * @param FilterChain $nextFilterChain
     */
    private function invokeCurrentFilter(Request $request, $nextFilterChain) {
        $this->filter->doFilter($request, $nextFilterChain);
    }

}