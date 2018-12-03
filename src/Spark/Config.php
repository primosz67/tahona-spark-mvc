<?php

namespace Spark;

use Spark\Common\IllegalArgumentException;
use Spark\Utils\Asserts;
use Spark\Utils\Collections;
use Spark\Utils\Objects;
use Spark\Utils\StringUtils;

/**
 *
 * App configuration as basic property provider with cache.
 *
 * Class Config
 * @package Spark
 */
class Config {

    public const SECURITY_PARAM = 'security.enabled';
    public const ERROR_HANDLING_ENABLED = 'error.errorHandling';

    public const DEV = 'dev';
    public const DEV_ENABLED = 'dev.enable';
    public const DEV_XDEBUG = 'dev.xdebug';

    public const MAIL_FROM_TITLE_KEY = 'mail.from.title';
    public const MAIL_FROM_EMAIL_KEY = 'mail.from.email';

    public const WEB_PAGE = 'web.page';
    public const APCU_CACHE_ENABLED = '';


    private $cache = array();

    /**
     * Make sure that property will not be replace in the code!
     *
     * @param $property String only like $config->getProperty("db.user");
     * @return mixed|null
     */
    public function getProperty($property, $default = null) {
        return Collections::getValueOrDefault($this->cache, $property, $default);
    }

    public function hasProperty($property) {
        return Collections::hasKey($this->cache, $property);
    }

    public function set($code, $value) {
        $this->cache[$code] = $value;
    }

    public function add($code, $value = array()) {
        Asserts::checkArray($value, 'Value must be array');

        if (isset($this->cache[$code])) {
            $this->cache[$code] = Collections::builder($this->cache[$code])
                ->addAll($value)
                ->get();
        } else {
            $this->cache[$code]= $value;
        }

    }

    /**
     * @param $property
     * @return bool
     */
    private function isPropertyCached($property) {
        return Collections::hasKey($this->cache, $property);
    }


    /**
     * @param $prefix
     * @param $properties
     */
    private function cacheProperty($prefix, $properties) {
        if (Objects::isArray($properties)) {
            foreach ($properties as $key => $prop) {
                $joined = StringUtils::join('.', array($prefix, $key), true);
                $this->cacheProperty($joined, $prop);
            }
        }
        //save parent
        $this->cache[$prefix] = $properties;
    }


}
