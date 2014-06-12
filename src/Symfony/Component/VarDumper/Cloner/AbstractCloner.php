<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

use Symfony\Component\VarDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static $defaultCasters = array(
        'o:Closure'        => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castClosure',
        'o:Reflector'      => 'Symfony\Component\VarDumper\Caster\ReflectionCaster::castReflector',

        'o:Doctrine\Common\Proxy\Proxy' => 'Symfony\Component\VarDumper\Caster\DoctrineCaster::castCommonProxy',
        'o:Doctrine\ORM\Proxy\Proxy'    => 'Symfony\Component\VarDumper\Caster\DoctrineCaster::castOrmProxy',

        'o:ErrorException' => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castErrorException',
        'o:Exception'      => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castException',
        'o:Symfony\Component\VarDumper\Exception\ThrowingCasterException'
                           => 'Symfony\Component\VarDumper\Caster\ExceptionCaster::castThrowingCasterException',

        'o:PDO'            => 'Symfony\Component\VarDumper\Caster\PdoCaster::castPdo',
        'o:PDOStatement'   => 'Symfony\Component\VarDumper\Caster\PdoCaster::castPdoStatement',

        'o:SplDoublyLinkedList' => 'Symfony\Component\VarDumper\Caster\SplCaster::castSplDoublyLinkedList',
        'o:SplFixedArray'       => 'Symfony\Component\VarDumper\Caster\SplCaster::castSplFixedArray',
        'o:SplHeap'             => 'Symfony\Component\VarDumper\Caster\SplCaster::castSplIterator',
        'o:SplObjectStorage'    => 'Symfony\Component\VarDumper\Caster\SplCaster::castSplObjectStorage',
        'o:SplPriorityQueue'    => 'Symfony\Component\VarDumper\Caster\SplCaster::castSplIterator',

        'r:curl'           => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castCurl',
        'r:dba'            => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castDba',
        'r:dba persistent' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castDba',
        'r:gd'             => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castGd',
        'r:mysql link'     => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castMysqlLink',
        'r:process'        => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castProcess',
        'r:stream'         => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castStream',
        'r:stream-context' => 'Symfony\Component\VarDumper\Caster\ResourceCaster::castStreamContext',
    );

    protected $maxItems = 1000;
    protected $maxString = 10000;

    private $casters = array();
    private $data = array(array(null));
    private $prevErrorHandler;

    /**
     * @param callable[]|null $casters A map of casters.
     *
     * @see addCasters
     */
    public function __construct(array $casters = null)
    {
        if (null === $casters) {
            $casters = static::$defaultCasters;
        }
        $this->addCasters($casters);
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or objects types to a callback.
     * Types are in the key, with a callable caster for value.
     * Objects class are to be prefixed with a `o:`,
     * resources type are to be prefixed with a `r:`,
     * see e.g. static::$defaultCasters.
     *
     * @param callable[] $casters A map of casters.
     */
    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback) {
            $this->casters[strtolower($type)][] = $callback;
        }
    }

    /**
     * Sets the maximum number of items to clone in nested structures.
     *
     * @param int $maxItems
     */
    public function setMaxItems($maxItems)
    {
        $this->maxItems = (int) $maxItems;
    }

    /**
     * Sets the maximum cloned length for strings.
     *
     * @param int $maxString
     */
    public function setMaxString($maxString)
    {
        $this->maxString = (int) $maxString;
    }

    /**
     * {@inheritdoc}
     */
    public function cloneVar($var)
    {
        $this->prevErrorHandler = set_error_handler(array($this, 'handleError'));
        try {
            if (!function_exists('iconv')) {
                $this->maxString = 0;
            }
            $data = $this->doClone($var);
        } catch (\Exception $e) {
        }
        restore_error_handler();
        $this->prevErrorHandler = null;

        if (isset($e)) {
            throw $e;
        }

        return new Data($data);
    }

    /**
     * Effectively clones the PHP variable.
     *
     * @param mixed $var Any PHP variable.
     *
     * @return array The cloned variable represented in an array.
     */
    abstract protected function doClone($var);

    /**
     * Casts an object to an array representation.
     *
     * @param string $class The class of the object.
     * @param object $obj   The object itself.
     *
     * @return array The object casted as array.
     */
    protected function castObject($class, $obj)
    {
        if (method_exists($obj, '__debugInfo')) {
            if (!$a = $this->callCaster(array($this, '__debugInfo'), $obj, array())) {
                $a = (array) $obj;
            }
        } else {
            $a = (array) $obj;
        }

        $p = array($class => $class)
            + class_parents($obj)
            + class_implements($obj)
            + array('*' => '*');

        foreach (array_reverse($p) as $p) {
            if (!empty($this->casters[$p = 'o:'.strtolower($p)])) {
                foreach ($this->casters[$p] as $p) {
                    $a = $this->callCaster($p, $obj, $a);
                }
            }
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param string   $type The type of the resource.
     * @param resource $res  The resource.
     *
     * @return array The resource casted as array.
     */
    protected function castResource($type, $res)
    {
        $a = array();

        if (!empty($this->casters['r:'.$type])) {
            foreach ($this->casters['r:'.$type] as $c) {
                $a = $this->callCaster($c, $res, $a);
            }
        }

        return $a;
    }

    /**
     * Calls a custom caster.
     *
     * @param callable        $callback The caster.
     * @param object|resource $obj      The object/resource being casted.
     * @param array           $a        The result of the previous cast for chained casters.
     *
     * @return array The casted object/resource.
     */
    private function callCaster($callback, $obj, $a)
    {
        try {
            // Ignore invalid $callback
            $cast = @call_user_func($callback, $obj, $a);

            if (is_array($cast)) {
                $a = $cast;
            }
        } catch (\Exception $e) {
            $a["\0~\0⚠"] = new ThrowingCasterException($callback, $e);
        }

        return $a;
    }

    /**
     * Special handling for errors: cloning must be fail-safe.
     *
     * @internal
     */
    public function handleError($type, $msg, $file, $line, $context)
    {
        if (E_RECOVERABLE_ERROR === $type || E_USER_ERROR === $type) {
            // Cloner never dies
            throw new \ErrorException($msg, 0, $type, $file, $line);
        }

        if ($this->prevErrorHandler) {
            return call_user_func_array($this->prevErrorHandler, array($type, $msg, $file, $line, $context));
        }

        return false;
    }
}
