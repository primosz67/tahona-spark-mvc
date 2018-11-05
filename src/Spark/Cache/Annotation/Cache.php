<?php
/**
 *
 *
 * Date: 09.10.16
 * Time: 20:18
 */

namespace Spark\Cache\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;


/**
 * @Annotation
 * @Target({"METHOD"})
 */
final class Cache {

    /** @var string */
    public $cache = 'cache';
    /** @var string */
    public $key = '';
    /**
     * @var integer
     */
    public $time = null;

}