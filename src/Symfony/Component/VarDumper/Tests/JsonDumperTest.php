<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests;

use Symfony\Component\VarDumper\Cloner\PhpCloner;
use Symfony\Component\VarDumper\Dumper\JsonDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class JsonDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        require __DIR__.'/Fixtures/dumb-var.php';

        $dumper = new JsonDumper();
        $cloner = new PhpCloner();
        $var['dumper'] = $dumper;
        $data = $cloner->cloneVar($var);

        $var['file'] = str_replace('\\', '\\\\', $var['file']);

        $json = array();
        $dumper->dump($data, function ($line, $depth) use (&$json) {
            $json[] = str_repeat('  ', $depth).$line;
        });
        $json = implode("\n", $json);
        $closureLabel = PHP_VERSION_ID >= 50400 ? 'public method' : 'function';

        $this->assertSame(
'{"_":"1:array:25",
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
  "res": {"_":"14:resource:stream",
    "wrapper_type": "plainfile",
    "stream_type": "dir",
    "mode": "r",
    "unread_bytes": 0,
    "seekable": true,
    "timed_out": false,
    "blocked": true,
    "eof": false,
    "options": []
  },
  "n`8": {"_":"24:resource:Unknown"},
  "obj": {"_":"25:Symfony\\\\Component\\\\VarDumper\\\\Tests\\\\Fixture\\\\DumbFoo",
    "foo": "foo",
    "+:bar": "bar"
  },
  "closure": {"_":"28:Closure",
    "~:reflection": "Closure [ <user> '.$closureLabel.' Symfony\\\\Component\\\\VarDumper\\\\Tests\\\\Fixture\\\\{closure} ] {\n  @@ '.$var['file'].' '.$var['line'].' - '.$var['line'].'\n\n  - Parameters [2] {\n    Parameter #0 [ <required> $a ]\n    Parameter #1 [ <optional> PDO or NULL &$b = NULL ]\n  }\n}\n"
  },
  "line": '.$var['line'].',
  "nobj": [
    {"_":"32:stdClass"}
  ],
  "recurs": [
    "R`34:33"
  ],
  "n`9": "R`35:3",
  "sobj": "r`36:25",
  "snobj": "R`37:32",
  "snobj2": "r`38:32",
  "file": "'.$var['file'].'",
  "dumper": {"_":"40:Symfony\\\\Component\\\\VarDumper\\\\Dumper\\\\JsonDumper",
    "*:position": 0,
    "*:refsPos": [],
    "*:refs": [],
    "*:line": "",
    "*:lineDumper": [
      "r`46:40",
      "echoLine"
    ],
    "*:outputStream": {"_":"48:resource:stream",
      "wrapper_type": "PHP",
      "stream_type": "Output",
      "mode": "wb",
      "unread_bytes": 0,
      "seekable": false,
      "uri": "php://output",
      "timed_out": false,
      "blocked": true,
      "eof": false,
      "options": []
    }
  },
  "__refs": {"33":[-34],"3":[-35],"25":[36],"32":[-37,38],"40":[46]}
}
',
            $json
        );
    }
}
