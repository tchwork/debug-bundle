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
      "PDO &$b": null
    },
    "~:file": "' . __FILE__ . '",
    "~:lines": "' . $v['line'] . '-' . $v['line'] . '"
  },
  "line": ' . $v['line'] . ',
  "nobj": {"_":"32:array:1",
    "0": "r`33:"
  },
  "recurs": {"_":"34:array:1",
    "0": "R`35:34"
  },
  "9": "R`36:",
  "sobj": "r`37:24",
  "snobj": {"_":"38:stdClass"},
  "snobj2": "r`39:33",
  "__refs": {"3":[-36],"34":[-35],"24":[37],"33":[-38,39]}
}',
            JsonDumper::get($v)
        );
    }
}
