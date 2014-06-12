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
        $data = $cloner->cloneVar($var);

        $var['file'] = str_replace('\\', '\\\\', $var['file']);

        $json = array();
        $dumper->dump($data, function ($line, $depth) use (&$json) {
            $json[] = str_repeat('  ', $depth).$line;
        });
        $json = implode("\n", $json);
        $closureLabel = PHP_VERSION_ID >= 50400 ? 'public method' : 'function';

        $this->assertSame(
'{"_":"1:array:24",
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
  "obj": {"_":"25:stdClass"},
  "closure": {"_":"26:Closure",
    "~:reflection": "Closure [ <user> '.$closureLabel.' {closure} ] {\n  @@ '.$var['file'].' '.$var['line'].' - '.$var['line'].'\n\n  - Parameters [2] {\n    Parameter #0 [ <required> $a ]\n    Parameter #1 [ <optional> PDO or NULL &$b = NULL ]\n  }\n}\n"
  },
  "line": '.$var['line'].',
  "nobj": [
    {"_":"30:stdClass"}
  ],
  "recurs": [
    "R`32:31"
  ],
  "n`9": "R`33:3",
  "sobj": "r`34:25",
  "snobj": "R`35:30",
  "snobj2": "r`36:30",
  "file": "'.$var['file'].'",
  "__refs": {"31":[-32],"3":[-33],"25":[34],"30":[-35,36]}
}
',
            $json
        );
    }
}
