<?php

namespace Patchwork\Tests\PHP;

use Patchwork\PHP\ErrorHandler;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    function testStart()
    {
        $f = tempnam('/', 'test');
        $this->assertTrue(false !== $f);

        error_reporting(E_ALL | E_STRICT);

        ErrorHandler::start($f);
        $h = ErrorHandler::getHandler();
        $h->tracedErrors = 0;
        $h->getLogger()->loggedGlobals = array();

        try
        {
            user_error('fake user error', E_USER_ERROR);
            $this->assertFalse( true );
        }
        catch (\Patchwork\PHP\RecoverableErrorException $e)
        {
            $h->handleException($e);
        }

        if (function_exists('xdebug_disable')) xdebug_disable();
        eval("\x01"); // Uncatchable E_COMPILE_WARNING
        if (function_exists('xdebug_disable')) xdebug_enable();

        error_reporting(0);
        @eval('abc'); // Parse error to populate error_get_last()
        $h->scream = E_PARSE;
        ErrorHandler::shutdown();
        error_reporting(E_ALL | E_STRICT);

        $h->unregister();

        $e = file_get_contents($f);

        unlink($f);

        $this->assertStringMatchesFormat(
'*** php-error ***
{"_":"1:array:3",
  "time": "%s %dus - %fms - %fms",
  "mem": "%d - %d",
  "data": {"_":"4:array:4",
    "mesg": "Uncaught exception: fake user error",
    "type": "E_USER_ERROR ' . __FILE__ . ':23",
    "level": "256/32767",
    "scope": {"_":"8:array:1",
      "0": {"_":"9:Patchwork\\\\PHP\\\\RecoverableErrorException",
        "scope": {"_":"10:array:2",
          "f": "' . $f . '",
          "h": {"_":"12:Patchwork\\\\PHP\\\\ErrorHandler",
            "scream": 4433,
            "thrownErrors": 0,
            "scopedErrors": 4867,
            "tracedErrors": 0,
            "*:logger": {"_":"17:Patchwork\\\\PHP\\\\Logger",
              "writeLock": true,
              "lineFormat": "%s",
              "loggedGlobals": [],
              "*:logStream": {"_":"21:resource:stream",
                "wrapper_type": "plainfile",
                "stream_type": "STDIO",
                "mode": "ab",
                "unread_bytes": 0,
                "seekable": true,
                "uri": "' . $f . '",
                "timed_out": false,
                "blocked": true,
                "eof": false
              },
              "*:prevTime": %f,
              "*:startTime": %f,
              "*:isFirstEvent": true
            },
            "*:loggedTraces": [],
            "*:registeredErrors": 32767
          }
        },
        "*:message": "fake user error",
        "*:code": 0,
        "*:file": "' . __FILE__ . '",
        "*:line": 23,
        "*:severity": "E_USER_ERROR"
      }
    }
  }
}
***
[%s] PHP Warning:  Unexpected character in input:  ' . "'\x01'" . ' (ASCII=1) state=0 in ' . __FILE__ . '(32) : eval()\'d code on line 1
*** php-error ***
{"_":"1:array:3",
  "time": "%s %dus - %fms - %fms",
  "mem": "%d - %d",
  "data": {"_":"4:array:3",
    "mesg": "syntax error, unexpected ' . (PHP_VERSION_ID >= 50400 ? 'end of file' : '$end') . '",
    "type": "E_PARSE ' . __FILE__ . '(36) : eval()\'d code:1",
    "level": "4/0"
  }
}
***
',
            $e
        );
    }
}
