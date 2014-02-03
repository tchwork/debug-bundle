<?php

namespace Patchwork\Tests\PHP;

use Patchwork\PHP\JsonDumper;

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

        $json = new JsonDumper;
        $json = $json->walk($v);

        $this->assertSame(
'{"_":"1:array:23",
  "number": 1,
  "0": 1.1,
  "const": null,
  "1": true,
  "2": false,
  "3": "n`NAN",
  "4": "n`INF",
  "5": "n`-INF",
  "6": "n`' . PHP_INT_MAX . '",
  "str": "déjà",
  "7": "b`é",
  "[]": [],
  "res": {"_":"14:resource:stream",
    "~:wrapper_type": "plainfile",
    "~:stream_type": "dir",
    "~:mode": "r",
    "~:unread_bytes": 0,
    "~:seekable": true,
    "~:timed_out": false,
    "~:blocked": true,
    "~:eof": false
  },
  "8": {"_":"23:resource:Unknown"},
  "obj": {"_":"24:stdClass"},
  "closure": {"_":"25:Closure",
    "~:reflection": "Closure [ <user> public method Patchwork\\\\Tests\\\\PHP\\\\{closure} ] {\n  @@ ' . __FILE__ . ' 22 - 22\n\n  - Parameters [2] {\n    Parameter #0 [ <required> $a ]\n    Parameter #1 [ <optional> PDO or NULL &$b = NULL ]\n  }\n}\n"
  },
  "line": ' . $v['line'] . ',
  "nobj": {"_":"28:array:1",
    "0": "r`29:29"
  },
  "recurs": {"_":"30:array:1",
    "0": "R`31:30"
  },
  "9": "R`32:",
  "sobj": "r`33:24",
  "snobj": {"_":"34:stdClass"},
  "snobj2": "r`35:29",
  "__refs": {"3":[-32],"30":[-31],"24":[33],"29":[-34,35]}
}',
            $json
        );
    }
}
