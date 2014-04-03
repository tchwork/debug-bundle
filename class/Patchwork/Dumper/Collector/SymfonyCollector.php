<?php

namespace Patchwork\Dumper\Collector;

class SymfonyCollector extends AbstractCollector
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
        $maxItems = $this->maxItems;
        $maxString = $this->maxString;

        for ($i = 0; $i < $len; ++$i) {
            $indexed = 1;
            $j = -1;
            $step = $queue[$i];
            foreach ($step as $k => $v) {
                if ($indexed && $k !== ++$j) {
                    $indexed = 0;
                }
                $zval = symfony_zval_info($k, $step);
                if ($zval['zval_isref']) {
                    $queue[$i][$k] =& $r;
                    unset($r);
                    if (isset($hardRefs[$h = $zval['zval_hash']])) {
                        $hardRefs[$h]->ref = ++$refs;
                        $queue[$i][$k] = $hardRefs[$h];
                        continue;
                    }
                }
                switch ($zval['type']) {
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
                            $r = (object) array('count' => $zval['array_count']);
                            $arrayRefs[$len] = $r;
                            $a = $v;
                        }
                        break;

                    case 'object':
                        if (empty($softRefs[$h = $zval['object_hash']])) {
                            $r = $softRefs[$h] = (object) array('class' => $zval['object_class']);
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
                        if (empty($softRefs[$h = $zval['resource_id']])) {
                            $r = $softRefs[$h] = (object) array('res' => $zval['resource_type']);
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
                    if ($zval['zval_isref']) {
                        if (isset($r->count)) {
                            $queue[$i][$k] = $hardRefs[$zval['zval_hash']] = $r;
                        } else {
                            $queue[$i][$k] = $hardRefs[$zval['zval_hash']] = (object) array('val' => $r);
                        }
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
                        } elseif (0 == $maxItems) {
                            $maxItems = $pos = count($a);
                        }
                        $queue[$len] = $a;
                        $r->pos = $len++;
                    }
                    $r = $a = null;
                } elseif ($zval['zval_isref']) {
                    $queue[$i][$k] = $hardRefs[$zval['zval_hash']] = (object) array('val' => $v);
                }
            }

            if (isset($arrayRefs[$i])) {
                $indexed and $arrayRefs[$i]->indexed = 1;
                unset($arrayRefs[$i]);
            }
        }

        return $queue;
    }
}
