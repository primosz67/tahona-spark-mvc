<?php
/**
 *
 *
 * Date: 23.08.14
 * Time: 16:42
 */

namespace Spark\View\Json;


class JsonResponseHelper {

    public static function responseError() {
        return array("RESPONSE" => "ERROR");
    }

    public static function responseOK() {
        return array("RESPONSE" => "OK");
    }
}