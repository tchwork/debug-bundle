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

        $this->assertSame(
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
    "~:args": {"_":"26:array:2",
      "0": "$a",
      "&$b": null
    },
    "~:file": "' . __FILE__ . '",
    "~:lines": "' . $v['line'] . '-' . $v['line'] . '"
  },
  "line": ' . $v['line'] . ',
  "recurs": {"_":"32:array:1",
    "0": "R`33:32"
  },
  "9": "R`34:",
  "sameobj": "r`35:24",
  "__refs": {"3":[-34],"32":[-33],"24":[35]}
}',
            JsonDumper::get($v)
        );
    }
}
