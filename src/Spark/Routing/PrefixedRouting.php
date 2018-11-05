<?php
/**
 *
 *
 * Date: 01.08.15
 * Time: 10:25
 */

namespace Spark\Routing;


use Spark\Utils\Asserts;
use Spark\Utils\Collections;
use Spark\Utils\Objects;

abstract class PrefixedRouting {

    /**
     * @return array
     */
    abstract protected function  getDefaultRoutes();

    /**
     * @return string
     */
    abstract protected function  getDefaultPrefix();

    public function getRouting($prefix = null) {
        $defaultRoutes = $this->getDefaultRoutes();

        Asserts::checkState(Collections::isNotEmpty($defaultRoutes));

        if (Objects::isNull($prefix)) {
            $prefix = $this->getDefaultPrefix();
        }

        $r = array();
        foreach ($defaultRoutes as $route => $definition) {
            $r[$prefix.$route] = $definition;
        }
        return $r;
    }

    /**
     * @return RouteHelper
     */
    protected function createRouteHelper() {
        return new RouteHelper();
    }


}