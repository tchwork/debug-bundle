<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper;

/**
 * Walker implements a mechanism to generically traverse any PHP variable.
 *
 * It takes internal references into account, recursive or non-recursive, without preempting any
 * special use of the discovered data. It exposes only one public method ->walk(), which triggers
 * the traversal.
 */
abstract class Walker
{
    protected

    $position = 0,
    $refMap = array(),
    $refPool = array(),
    $valPool = array(),
    $objPool = array(),
    $ignoreError = false,
    $prevErrorHandler = null,
    $hasSymfonyZvalInfo;

    protected static $tag;

    abstract protected function dumpObject($info);
    abstract protected function dumpResource($info);
    abstract protected function dumpScalar($val);
    abstract protected function dumpString($str, $isKey);
    abstract protected function dumpHash($type, &$array, $len);
    abstract protected function dumpRef($isSoft, $position, $info);


    public function walk(&$ref)
    {
        if (empty(self::$tag))
        {
            self::$tag = new WalkerRefTag;
            self::$tag->tag = self::$tag;
        }

        $this->position = 0;
        $this->prevErrorHandler = set_error_handler(array($this, 'handleError'));
        function_exists('symfony_zval_info') and $this->hasSymfonyZvalInfo = true;

        try
        {
            $this->walkRef($ref, $this->getInfo($ref, $ref), null);
        }
        catch (\Exception $e) {}

        restore_error_handler();
        $this->prevErrorHandler = null;

        $this->refPool =
            $this->valPool =
            $this->objPool =
            $this->refMap = array();

        if (isset($e)) throw $e;
    }

    protected function walkRef(&$ref, $info, $key)
    {
        ++$this->position;

        if ($info instanceof WalkerRefTag)
        {
            if ($ref->position)
            {
                $this->refMap[-$this->position] = $ref->position;
                $this->dumpRef(false, $ref->position, $ref->info);
            }
            else
            {
                if ($ref === self::$tag) $ref = clone self::$tag;
                $ref->refs[] = -$this->position;
                $this->dumpRef(false, 0, null);
            }
        }
        else
        {
            $this->refPool[$this->position] =& $ref;
            $this->valPool[$this->position] = $info['value'];
            ($ref instanceof WalkerRefTag and $ref->tag === self::$tag) or $ref = self::$tag;

            switch ($info['type'])
            {
            default:
            case 'integer': $this->dumpScalar($info['value']); break;
            case 'string': $this->dumpString($info['value'], false); break;
            case 'array': $this->dumpHash('array', $info['value'], $info['array_count']); break;

            case 'object': $h = $info['object_hash']; // No break;
            case 'resource': isset($h) or $h = $info['resource_id'];

                if (isset($this->objPool[$h]))
                {
                    $this->dumpRef(true, $this->refMap[$this->position] = $this->objPool[$h], $info);

                    break;
                }

                $this->objPool[$h] = $this->position;

                if (isset($h[0])) $this->dumpObject($info);
                else $this->dumpResource($info);
            }
        }
    }

    protected function cleanRefPools()
    {
        $refs = array();

        foreach ($this->refPool as $position => &$ref)
        {
            if ($ref !== self::$tag && $ref instanceof WalkerRefTag && $ref->tag === self::$tag && $ref->refs)
            {
                $refs[$position] = $ref->refs;
            }

            $ref = $this->valPool[$position];
        }
        $this->refPool = array();

        unset($this->refMap[0]);
        foreach ($this->refMap as $p => $position) $refs[$position][] = $p;
        unset($refs[0]);

        return $refs;
    }

    /**
     * @internal
     */
    public function handleError($type, $msg, $file, $line, $context)
    {
        if (true !== $this->ignoreError)
        {
            if (E_RECOVERABLE_ERROR === $type || E_USER_ERROR === $type) // Walker never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);

            if (! $this->prevErrorHandler) return false;

            return call_user_func_array($this->prevErrorHandler, array($type, $msg, $file, $line, $context));
        }

        $this->ignoreError = $msg;
    }

    protected function getInfo(&$ref, $val)
    {
        if ($val instanceof WalkerRefTag && $val->tag === self::$tag)
        {
            return $val;
        }
        else if (isset($this->hasSymfonyZvalInfo))
        {
            $info = symfony_zval_info(0, array(&$ref));
        }
        else
        {
            $info = array('type' => gettype($val));

            switch ($ref['type'])
            {
            case 'array':
                $ref['array_count'] = count($val);
                break;

            case 'object':
                $ref['object_class'] = get_class($val);
                $ref['object_hash'] = spl_object_hash($val);
                break;

            case 'unknown type':
                $ref['type'] = 'resource'; // No break;
            case 'resource':
                $ref['resource_id'] = (int) substr((string) $val, 13);
                $ref['resource_type'] = get_resource_type($val);
                break;
            }
        }

        $info['value'] = $val;

        return $info;
    }
}

class WalkerRefTag
{
    public $tag = null;
    public $position = 0;
    public $hash = null;
    public $refs = array();
}
