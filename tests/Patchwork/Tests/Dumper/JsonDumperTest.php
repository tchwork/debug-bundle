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
        $json = implode(PHP_EOL, $json);

        $this->assertSame(
'{"_":"1:breadthQueue:",
  "n`2": {"_":"2:array:23",
    "number": 1,
    "n`0": 1.1,
    "const": null,
    "n`1": true,
    "n`2": false,
    "n`3": "n`NAN",
    "n`4": "n`INF",
    "n`5": "n`-INF",
    "n`6": "n`' . PHP_INT_MAX . '",
    "str": "déjà",
    "n`7": "b`é",
    "[]": [],
    "res": "R`15:",
    "n`8": "R`16:",
    "obj": "R`17:",
    "closure": "R`18:",
    "line": ' . $v['line'] . ',
    "nobj": "R`20:",
    "recurs": "R`21:",
    "n`9": "R`22:",
    "sobj": "R`23:",
    "snobj": "R`24:",
    "snobj2": "R`25:"
  },
  "n`15": {"_":"26:resource:stream",
    "~:wrapper_type": "plainfile",
    "~:stream_type": "dir",
    "~:mode": "r",
    "~:unread_bytes": 0,
    "~:seekable": true,
    "~:timed_out": false,
    "~:blocked": true,
    "~:eof": false
  },
  "n`16": {"_":"35:resource:Unknown"},
  "n`17": {"_":"36:stdClass"},
  "n`18": {"_":"37:Closure",
    "~:reflection": "Closure [ <user> public method Patchwork\\\\Tests\\\\Dumper\\\\{closure} ] {\n  @@ ' . __FILE__ . ' 22 - 22\n\n  - Parameters [2] {\n    Parameter #0 [ <required> $a ]\n    Parameter #1 [ <optional> PDO or NULL &$b = NULL ]\n  }\n}\n"
  },
  "n`20": {"_":"39:array:1",
    "n`0": "R`40:"
  },
  "n`21": {"_":"41:array:1",
    "n`0": "R`42:"
  },
  "n`23": "r`43:36",
  "n`24": {"_":"44:stdClass"},
  "n`25": "r`45:44",
  "__refs": {"4":[-22],"21":[-42],"24":[-40],"36":[43],"44":[45]}
}
',
            $json
        );
    }
}
