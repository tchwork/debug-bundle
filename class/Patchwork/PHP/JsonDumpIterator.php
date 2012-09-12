<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2012 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\PHP;

class JsonDumpIterator implements \Iterator
{
    protected

    $stream,
    $currentJson,
    $currentKey = 0,
    $nextLine = false,
    $buffer = array(),
    $bufferDepth = 0,
    $msgMap = array(
        'notice' => 'E_NOTICE',
        'warning' => 'E_WARNING',
        'deprecated' => 'E_DEPRECATED',
        'fatal error' => 'E_ERROR',
        'parse error' => 'E_PARSE',
        'strict standards' => 'E_STRICT',
        'catchable fatal error' => 'E_RECOVERABLE_ERROR',
    );


    function __construct($stream)
    {
        $this->stream = $stream;
    }

    function getStream()
    {
        return $this->stream;
    }

    function rewind()
    {
        // Not rewindable iterator
        if (false === $this->nextLine) $this->nextLine = fgets($this->stream);
        $this->next();
    }

    function valid()
    {
        return false !== $this->currentJson;
    }

    function current()
    {
        return $this->currentJson;
    }

    function key()
    {
        return $this->currentKey;
    }

    function next()
    {
        if (false === $this->nextLine)
        {
            $this->currentJson = false;
            return;
        }

        while (false !== $line = $this->nextLine)
        {
            $this->nextLine = fgets($this->stream);

            foreach ($this->parseLine($line, $this->nextLine) as $line)
            {
                if (null === $line) continue;

                if ('*** ' === substr($line, 0, 4))
                {
                    $this->buffer[++$this->bufferDepth] = array(substr($line, 4, -4) => '');
                }
                else if ($this->bufferDepth)
                {
                    if ('***' === $line)
                    {
                        ++$this->currentKey;
                        $this->currentJson = array(
                            'type' => key($this->buffer[$this->bufferDepth]),
                            'json' => implode('', $this->buffer[$this->bufferDepth]),
                        );
                        unset($this->buffer[$this->bufferDepth--]);
                        return;
                    }
                    else
                    {
                        $this->buffer[$this->bufferDepth][] = $line . "\n";
                    }
                }
                else
                {
                    user_error('Invalid JsonDump stream', E_USER_WARNING);
                }
            }
        }

        throw new JsonDumpIteratorException; // The JSON is only partial
    }

    function jsonStr($s)
    {
        if (false !== $r = @json_encode($s)) return $r;
        return json_encode(utf8_encode($s));
    }


    protected function parseLine($line, $next_line)
    {
        if ($offset = $this->getPhpLineOffset($line))
        {
            return $this->parsePhpLine($line, $next_line, $offset);
        }
        else
        {
            return array(rtrim($line));
        }
    }

    protected function getPhpLineOffset($line)
    {
        if ('' !== $line && '[' === $line[0] && (false !== $offset = strpos($line, ']')) && '] PHP ' === substr($line, $offset, 6))
        {
            return $offset + 6;
        }
    }

    protected function parsePhpLine($line, $next_line, $offset)
    {
        if (preg_match("' on line \d+$'", $line))
        {
            $line = $this->parsePhpError($line, $offset);
            $line = array(
                '*** php-error ***',
                '{',
                '  "time": ' . $this->jsonStr($line['date']) . ',',
                '  "data": {',
                '    "mesg": ' . $this->jsonStr($line['message']) . ',',
                '    "code": ' . $this->jsonStr("{$line['type']} {$line['file']}:{$line['line']}") . ',',
                '    "level": ' . $this->jsonStr(constant($line['type']) . '/-1'),
            );

            if ("Stack trace:" === substr(rtrim($next_line), $offset))
            {
                $line[count($line) - 1] .= ',';
            }
        }
        else
        {
            // Xdebug inserted stack trace

            $line = substr(rtrim($line), $offset);

            if ("Stack trace:" === $line)
            {
                $line = array('    "trace": {');
            }
            else
            {
                // TODO: more extensive parsing of dumped arguments using token_get_all() / client-side parsing?

                preg_match("' +(\d+)\. (.+?)\((.*)\) (.*)$'", $line, $line);

                $line = array(
                    '      "' . $line[1] . '": {',
                    '        "call": ' . $this->jsonStr("{$line[2]}() {$line[4]}") . ('' !== $line[3] ? ',' : ''),
                    '' !== $line[3] ? '        "args": ' . $this->jsonStr($line[3]) : null,
                    '      }'
                );

                if (!$this->getPhpLineOffset($next_line) || preg_match("' on line \d+$'", $next_line))
                {
                    $line[] = '    }';
                }
                else
                {
                    $line[count($line) - 1] .= ',';
                }
            }
        }

        if (!$this->getPhpLineOffset($next_line) || preg_match("' on line \d+$'", $next_line))
        {
            $line[] = '  }';
            $line[] = '}    ';
            $line[] = '***';
        }

        return $line;
    }

    protected function parsePhpError($line, $offset)
    {
        $m = strpos($line, ':', $offset + 1);

        $e = array(
            'date' => substr($line, 1, $offset - 7),
            'type' => substr($line, $offset, $m - $offset),
            'message' => rtrim(substr($line, $m + 3)),
            'file' => '',
            'line' => 0,
        );

        $e['date'] = date('c', strtotime($e['date']));

        if (isset($this->msgMap[$m = strtolower($e['type'])]))
        {
            $e['type'] = $this->msgMap[$m];
        }

        if (preg_match('/^(.*) in (.*) on line (\d+)$/s', $e['message'], $m))
        {
            $e['message'] = $m[1];
            $e['file'] = $m[2];
            $e['line'] = $m[3];
        }

        return $e;
    }
}

class JsonDumpIteratorException extends \Exception
{
}
