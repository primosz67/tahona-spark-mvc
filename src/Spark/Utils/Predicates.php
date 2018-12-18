<?php
/**
 *
 *
 * Date: 26.03.15
 * Time: 20:20
 */

namespace Spark\Utils;

class Predicates {

    public static function alwaysTrue() {
        return function ($obj) {
            return true;
        };
    }

    public static function notNull() {
        return function ($obj) {
            return Objects::isNotNull($obj);
        };
    }

    public static function notEmpty() {
        return function ($obj) {
            return Collections::isNotEmpty($obj);
        };
    }

    public static function not(\Closure $pred) {
        return function ($obj, $k) use ($pred) {
            return false === $pred($obj, $k);
        };
    }

    public static function hasArrayKey($field) {
        return function ($arr) use ($field) {
            return Collections::isNotEmpty($arr) && Collections::hasKey($arr, $field);
        };
    }

    public static function notIn(array $defined) {
        return function ($x) use ($defined) {
            return !Collections::contains($x, $defined);
        };
    }

    public static function in(array $defined) {
        return function ($x) use ($defined) {
            return Collections::contains($x, $defined);
        };
    }

    public static function compute(\Closure $function, \Closure $predicate) {
        return function ($x) use ($function, $predicate) {
            return $predicate($function($x));
        };
    }

    public static function equals($defined) {
        return function ($x) use ($defined) {
            return $x == $defined;
        };
    }

    public static function isArray(): \Closure {
        return function ($x) {
            return Objects::isArray($x);
        };
    }

    public static function isFalse(): \Closure {
        return function ($x) {
            return BooleanUtils::isFalse($x);
        };
    }

    public static function testOR(array $predicatesArray): callable {
        return function ($x) use ($predicatesArray) {
            foreach ($predicatesArray as $pred) {
                if ($pred($x)) {
                    return true;
                }
            }
            return false;
        };
    }
}