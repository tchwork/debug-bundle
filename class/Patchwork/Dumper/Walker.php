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
    $prevErrorHandler = null;

    protected static $tag;

    abstract protected function dumpObject($obj, $hash);
    abstract protected function dumpResource($res);
    abstract protected function dumpScalar($val);
    abstract protected function dumpString($str, $isKey);
    abstract protected function dumpHash($type, $array);
    abstract protected function dumpRef($isSoft, $position, $hash);


    public function walk(&$ref)
    {
        if (empty(self::$tag))
        {
            self::$tag = new WalkerRefTag;
            self::$tag->tag = self::$tag;
        }

        $this->position = 0;
        $this->prevErrorHandler = set_error_handler(array($this, 'handleError'));

        try
        {
            $val= $ref;
            $type = gettype($val);
            $this->walkRef($ref, $val, $type, null);
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

    protected function walkRef(&$ref, $val, $type, $key)
    {
        ++$this->position;

        if ($val instanceof WalkerRefTag && $val->tag === self::$tag)
        {
            if ($val->position)
            {
                $this->refMap[-$this->position] = $val->position;
                $this->dumpRef(false, $val->position, $val->hash);
            }
            else
            {
                if ($val === self::$tag) $ref = clone self::$tag;
                $ref->refs[] = -$this->position;
                $this->dumpRef(false, 0, null);
            }
        }
        else
        {
            $this->refPool[$this->position] =& $ref;
            $this->valPool[$this->position] = $val;
            if (! $ref instanceof WalkerRefTag || $ref->tag !== self::$tag) $ref = self::$tag;

            switch ($type)
            {
            default:
            case 'integer': $this->dumpScalar($val); break;
            case 'string': $this->dumpString($val, false); break;
            case 'array': $this->dumpHash($type, $val); break;

            case 'object': $h = pack('H*', spl_object_hash($val)); // No break;
            case 'unknown type':
            case 'resource': isset($h) or $h = (int) substr((string) $val, 13);

                if (isset($this->objPool[$h]))
                {
                    $this->dumpRef(true, $this->refMap[$this->position] = $this->objPool[$h], $h);

                    break;
                }

                $this->objPool[$h] = $this->position;

                if (isset($h[0])) $this->dumpObject($val, $h);
                else $this->dumpResource($val);
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
}

class WalkerRefTag
{
    public $tag = null;
    public $position = 0;
    public $hash = null;
    public $refs = array();
}
