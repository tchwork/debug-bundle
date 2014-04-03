<?php

namespace Patchwork\Tests\Dumper;

use Patchwork\Dumper\JsonDumper;

class JsonDumperTest extends \PHPUnit_Framework_TestCase
{
    function testGet()
    {
        $g = opendir('.');
        $h = opendir('.');
        closedir($h);

        $v = array(
            'number' => 1, 1.1,
            'const' => null, true, false, NAN, INF, -INF, PHP_INT_MAX,
            'str' => "déjà", "\xE9",
            '[]' => array(),
            'res' => $g, $h,
            'obj' => (object) array(),
            'closure' => function($a, \PDO &$b = null) {}, 'line' => __LINE__,
            'nobj' => array((object) array()),
        );

        $r = array();
        $r[] =& $r;

        $v['recurs'] =& $r;
        $v[] =& $v[0];
        $v['sobj'] = $v['obj'];
        $v['snobj'] =& $v['nobj'][0];
        $v['snobj2'] = $v['nobj'][0];

        $json = array();
        $dumper = new JsonDumper(function ($line, $depth) use (&$json) {
            $json[] = str_repeat('  ', $depth) . $line;
        });
        $dumper->walk($v);
        $json = implode("\n", $json);

        $this->assertSame(
'{"_":"1:array:23,
  "number": 1,
  "n`0": 1.1,
  "const": null,
  "n`1": true,
  "n`2": false,
  "n`3": "n`NAN",
  "n`4": "n`INF",
  "n`5": "n`-INF",
  "n`6": "n`'.PHP_INT_MAX.'",
  "str": "déjà",
  "n`7": "b`é",
  "[]": [],
  "res": {"_":"14:resource:stream,
    "~:wrapper_type": "plainfile",
    "~:stream_type": "dir",
    "~:mode": "r",
    "~:unread_bytes": 0,
    "~:seekable": true,
    "~:timed_out": false,
    "~:blocked": true,
    "~:eof": false
  },
  "n`8": {"_":"23:resource:Unknown},
  "obj": {"_":"24:stdClass},
  "closure": {"_":"25:Closure,
    "~:reflection": "Closure [ <user> public method Patchwork\\\\Tests\\\\Dumper\\\\{closure} ] {\n  @@ '.__FILE__.' 22 - 22\n\n  - Parameters [2] {\n    Parameter #0 [ <required> $a ]\n    Parameter #1 [ <optional> PDO or NULL &$b = NULL ]\n  }\n}\n"
  },
  "line": 22,
  "nobj": [
    {"_":"29:stdClass}
  ],
  "recurs": [
    "R`31:30"
  ],
  "n`9": "R`32:3",
  "sobj": "r`33:24",
  "snobj": "R`34:29",
  "snobj2": "r`35:29",
  "__refs": {"30":[31],"3":[32],"24":[-33],"29":[34,-35]}
}
',
            $json
        );
    }
}
