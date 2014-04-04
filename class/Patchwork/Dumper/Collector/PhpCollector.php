<?php

namespace Patchwork\Dumper\Collector;

use stdClass;

class PhpCollector extends AbstractCollector
{
    protected function doCollect($var)
    {
        $i = 0;
        $len = 1;
        $pos = 1;
        $refs = 0;
        $queue = array(array($var));
        $arrayRefs = array();
        $hardRefs = array();
        $softRefs = array();
        $values = array();
        $maxItems = $this->maxItems;
        $maxString = $this->maxString;
        $cookie = (object) array();
        $isRef = false;

        for ($i = 0; $i < $len; ++$i) {
            $indexed = 1;
            $j = -1;
            $step = $queue[$i];
            foreach ($step as $k => $v) {
                if ($indexed && $k !== ++$j) {
                    $indexed = 0;
                }
                $step[$k] = $cookie;
                if ($queue[$i][$k] === $cookie) {
                    $queue[$i][$k] =& $r;
                    unset($r);
                    if ($v instanceof stdClass && isset($hardRefs[spl_object_hash($v)])) {
                        $v->ref = ++$refs;
                        $step[$k] = $queue[$i][$k] = $v;
                        continue;
                    }
                    $isRef = true;
                }
                switch (gettype($v)) {
                    case 'string':
                        if (isset($v[0]) && !preg_match('//u', $v)) {
                            if (0 < $maxString && 0 < $cut = strlen($v) - $maxString) {
                                $r = substr_replace($v, '', 0, $maxString - 1);
                                $r = (object) array('cut' => $cut + 1, 'bin' => iconv('CP1252', 'UTF-8', $r));
                            } else {
                                $r = (object) array('bin' => iconv('CP1252', 'UTF-8', $v));
                            }
                        } elseif (0 < $maxString && isset($v[1+($maxString>>2)]) && 0 < $cut = iconv_strlen($v, 'UTF-8') - $maxString) {
                            $r = iconv_substr($v, 0, $maxString - 1, 'UTF-8');
                            $r = (object) array('cut' => $cut + 1, 'str' => $r);
                        }
                        break;

                    case 'array':
                        if ($v) {
                            $r = (object) array('count' => count($v));
                            $arrayRefs[$len] = $r;
                            $a = $v;
                        }
                        break;

                    case 'object':
                        if (empty($softRefs[$h = spl_object_hash($v)])) {
                            $r = $softRefs[$h] = (object) array('class' => get_class($v));
                            if (0 >= $maxItems || $pos < $maxItems) {
                                $a = $this->castObject($r->class, $v);
                            } else {
                                $r->cut = -1;
                            }
                        } else {
                            $r = $softRefs[$h];
                            $r->ref = ++$refs;
                        }
                        break;

                    case 'resource':
                    case 'unknown type':
                        if (empty($softRefs[$h = (int) substr_replace($v, '', 0, 13)])) {
                            $r = $softRefs[$h] = (object) array('res' => @get_resource_type($v));
                            if (0 >= $maxItems || $pos < $maxItems) {
                                $a = $this->castResource($r->res, $v);
                            } else {
                                $r->cut = -1;
                            }
                        } else {
                            $r = $softRefs[$h];
                            $r->ref = ++$refs;
                        }
                        break;
                }

                if (isset($r)) {
                    if ($isRef) {
                        if (isset($r->count)) {
                            $step[$k] = $r;
                        } else {
                            $step[$k] = (object) array('val' => $r);
                        }
                        $h = spl_object_hash($step[$k]);
                        $queue[$i][$k] = $hardRefs[$h] =& $step[$k];
                        $values[$h] = $v;
                        $isRef = false;
                    } else {
                        $queue[$i][$k] = $r;
                    }

                    if ($a) {
                        if (0 < $maxItems) {
                            $k = count($a);
                            if ($pos < $maxItems) {
                                if ($maxItems < $pos += $k) {
                                    $a = array_slice($a, 0, $maxItems - $pos);
                                    $r->cut = $pos - $maxItems;
                                }
                            } else {
                                $r->cut = $k;
                                $r = $a = null;
                                unset($arrayRefs[$len]);
                                continue;
                            }
                        } elseif (-1 == $maxItems) {
                            $maxItems = $pos = count($a);
                        }
                        $queue[$len] = $a;
                        $r->pos = $len++;
                    }
                    $r = $a = null;
                } elseif ($isRef) {
                    $step[$k] = $queue[$i][$k] = $v = (object) array('val' => $v);
                    $h = spl_object_hash($v);
                    $hardRefs[$h] =& $step[$k];
                    $values[$h] = $v->val;
                    $isRef = false;
                }
            }

            if (isset($arrayRefs[$i])) {
                $indexed and $arrayRefs[$i]->indexed = 1;
                unset($arrayRefs[$i]);
            }
        }

        foreach ($values as $h => $v) {
            $hardRefs[$h] = $v;
        }

        return $queue;
    }
}
