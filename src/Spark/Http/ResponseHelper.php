<?php
/**
 *
 *
 * Date: 09.10.14
 * Time: 19:24
 */

namespace Spark\Http;


use Spark\Common\Data\ContentType;

class ResponseHelper {

    /**
     * @param HttpCode $code
     */
    public static function setCode(HttpCode $code) {
        http_response_code($code->getCode());
    }

    public static function setContentType(ContentType $contentType) {
        header('Content-Type: ' . $contentType->getType());
    }

    public static function setHeader($headerKey, $headerValue) {
        header($headerKey.":".$headerValue);
    }

} 