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
            'closure' => function($a, &$b = null) {}, 'line' => __LINE__,
        );

        $r = array();
        $r[] =& $r;

        $v['recurs'] =& $r;
        $v[] =& $v[0];
        $v['sameobj'] = $v['obj'];

        $this->assertSame( JsonDumper::get($v),
'{"_":"1:array:20",
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
    "wrapper_type": "plainfile",
    "stream_type": "dir",
    "mode": "r",
    "unread_bytes": 0,
    "seekable": true,
    "timed_out": false,
    "blocked": true,
    "eof": false
  },
  "8": {"_":"23:resource:Unknown"},
  "obj": {"_":"24:stdClass"},
  "closure": {"_":"25:Closure",
    "0": "$a",
    "&$b": null,
    "file": "' . __FILE__ . '",
    "lines": "' . $v['line'] . '-' . $v['line'] . '"
  },
  "line": ' . $v['line'] . ',
  "recurs": {"_":"31:array:1",
    "0": "R`32:31"
  },
  "9": "R`33:",
  "sameobj": "r`34:24",
  "__refs": {"3":[-33],"31":[-32],"24":[34]}
}'
        );
    }
}
