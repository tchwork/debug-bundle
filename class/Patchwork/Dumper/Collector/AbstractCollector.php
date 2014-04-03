<?php

namespace Patchwork\Dumper\Collector;

use Patchwork\Dumper\Exception\ThrowingCasterException;

abstract class AbstractCollector implements CollectorInterface
{
    static

    $defaultCasters = array(
        'o:Closure'        => 'Patchwork\Dumper\Caster\BaseCaster::castClosure',
        'o:Doctrine\Common\Proxy\Proxy'
                           => 'Patchwork\Dumper\Caster\DoctrineCaster::castCommonProxy',
        'o:Doctrine\ORM\Proxy\Proxy'
                           => 'Patchwork\Dumper\Caster\DoctrineCaster::castOrmProxy',
        'o:ErrorException' => 'Patchwork\Dumper\Caster\ExceptionCaster::castErrorException',
        'o:Exception'      => 'Patchwork\Dumper\Caster\ExceptionCaster::castException',
        'o:Patchwork\Debug\InDepthRecoverableErrorException'
                           => 'Patchwork\Dumper\Caster\ExceptionCaster::castInDepthException',
        'o:Patchwork\Dumper\ThrowingCasterException'
                           => 'Patchwork\Dumper\Caster\ExceptionCaster::castThrowingCasterException',
        'o:PDO'            => 'Patchwork\Dumper\Caster\PdoCaster::castPdo',
        'o:PDOStatement'   => 'Patchwork\Dumper\Caster\PdoCaster::castPdoStatement',
        'o:Reflector'      => 'Patchwork\Dumper\Caster\BaseCaster::castReflector',
        'o:SplDoublyLinkedList' => 'Patchwork\Dumper\Caster\SplCaster::castSplDoublyLinkedList',
        'o:SplFixedArray'       => 'Patchwork\Dumper\Caster\SplCaster::castSplFixedArray',
        'o:SplHeap'             => 'Patchwork\Dumper\Caster\SplCaster::castIterator',
        'o:SplObjectStorage'    => 'Patchwork\Dumper\Caster\SplCaster::castSplObjectStorage',
        'o:SplPriorityQueue'    => 'Patchwork\Dumper\Caster\SplCaster::castIterator',

        'r:dba'            => 'Patchwork\Dumper\Caster\BaseCaster::castDba',
        'r:dba persistent' => 'Patchwork\Dumper\Caster\BaseCaster::castDba',
        'r:gd'             => 'Patchwork\Dumper\Caster\BaseCaster::castGd',
        'r:mysql link'     => 'Patchwork\Dumper\Caster\BaseCaster::castMysqlLink',
        'r:process'        => 'Patchwork\Dumper\Caster\BaseCaster::castProcess',
        'r:stream'         => 'Patchwork\Dumper\Caster\BaseCaster::castStream',
    );

    protected $maxItems = 500;
    protected $maxString = 5000;

    private $casters = array();
    private $data = array(array(null));
    private $prevErrorHandler = null;


    public function __construct(array $defaultCasters = null)
    {
        isset($defaultCasters) or $defaultCasters = static::$defaultCasters;
        $this->addCasters($defaultCasters);
    }

    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback) {
            $this->casters[strtolower($type)][] = $callback;
        }
    }

    public function setMaxItems($maxItems)
    {
        $this->maxItems = (int) $maxItems;
    }

    public function setMaxString($maxString)
    {
        $this->maxString = (int) $maxString;
    }

    public function collect($var)
    {
        $this->prevErrorHandler = set_error_handler(array($this, 'handleError'));
        try {
            $data = $this->doCollect($var);
        } catch (\Exception $e) {
            restore_error_handler();

            throw $e;
        }
        restore_error_handler();
        $this->prevErrorHandler = null;

        return $data;
    }

    abstract protected function doCollect($var);

    protected function castObject($class, $obj)
    {
        if (method_exists($obj, '__debugInfo')) {
            $a = array();
            if (!$this->callCaster(array($this, '__debugInfo'), $obj, $a)) {
                $a = (array) $obj;
            }
        }
        else {
            $a = (array) $obj;
        }

        $p = array($class => $class)
            + class_parents($obj)
            + class_implements($obj)
            + array('*' => '*');

        foreach (array_reverse($p) as $p) {
            if (!empty($this->casters[$p = 'o:'.strtolower($p)])) {
                foreach ($this->casters[$p] as $p) {
                    $this->callCaster($p, $obj, $a);
                }
            }
        }

        return $a;
    }

    protected function castResource($type, $res)
    {
        $a = array();
        $b = array();

        if (!empty($this->casters['r:'.$type])) {
            foreach ($this->casters['r:'.$type] as $c) {
                $this->callCaster($c, $res, $b);
            }
        }

        foreach ($b as $b => $c) {
            if (0 !== strpos($b, "\0~\0")) {
                $b = "\0~\0".$b;
            }
            $a[$b] = $c;
        }

        return $a;
    }

    private function callCaster($callback, $obj, &$a)
    {
        try {
            // Ignore invalid $callback
            $callback = @call_user_func($callback, $obj, $a);

            if (is_array($callback)) {
                $a = $callback;

                return true;
            }
        } catch (\Exception $e) {
            $a["\0~\0⚠"] = new ThrowingCasterException($callback, $e);
        }
    }

    /**
     * @internal
     */
    public function handleError($type, $msg, $file, $line, $context)
    {
        if (E_RECOVERABLE_ERROR === $type || E_USER_ERROR === $type) {
            // Dumper never dies
            throw new \ErrorException($msg, 0, $type, $file, $line);
        }

        if ($this->prevErrorHandler) {
            return call_user_func_array($this->prevErrorHandler, array($type, $msg, $file, $line, $context));
        }

        return false;
    }
}
