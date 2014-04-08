<?php

$g = opendir('.');
$h = opendir('.');
closedir($h);

$var = array(
    'number' => 1, 1.1,
    'const' => null, true, false, NAN, INF, -INF, PHP_INT_MAX,
    'str' => "déjà", "\xE9",
    '[]' => array(),
    'res' => $g,
    $h,
    'obj' => (object) array(),
    'closure' => function ($a, \PDO &$b = null) {},
    'line' => __LINE__ - 1,
    'nobj' => array((object) array()),
);

$r = array();
$r[] =& $r;

$var['recurs'] =& $r;
$var[] =& $var[0];
$var['sobj'] = $var['obj'];
$var['snobj'] =& $var['nobj'][0];
$var['snobj2'] = $var['nobj'][0];
$var['file'] = __FILE__;

unset($g, $h, $r);
