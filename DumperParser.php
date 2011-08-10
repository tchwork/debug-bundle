<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/

namespace Patchwork\PHP;

class DumperParser
{
    protected

    $indentLevel = 0,
    $indentStack = array(),
    $inString = false,
    $tokens = array();


    function tokenizeLine($a)
    {
        $this->tokens = array();
        $a = rtrim($a, "\r\n");

        if ($this->inString)
        {
            $this->push('indent', substr($a, 0, $this->indentLevel+2));

            $a = substr($a, $this->indentLevel+2);

            if ('"""' === substr($a, -3))
            {
                $a = substr($a, 0, -3);
                $this->inString = false;
            }
            // TODO: ...""" => ...

            $this->push('string', $a);
        }
        else
        {
            if (0 !== $this->indentLevel)
            {
                $a = substr($a, $this->indentLevel-2);

                if ($a !== ltrim($a, ']}'))
                {
                    $this->indentLevel -= 2;
                    array_pop($this->indentStack);
                }
                else $a = substr($a, 2);

                $this->indentLevel && $this->push('indent', str_repeat(' ', $this->indentLevel));
            }

            if ('"' === $a[0])
            {
                $i = strrpos($a, '"', 1);
                $kv = strpos(substr($a, 1, $i - 1), '" => "');
                false !== $kv && $i = $kv + 1;

                $kv = array(substr($a, 0, $i+1));

                if (false !== $i = strpos($a, ' => ', $i+1))
                {
                    $kv[1] = substr($a, $i+4);
                }
            }
            else $kv = explode(' => ', $a);

            $i = isset($kv[1]) ? ' key ' . end($this->indentStack) : '';

            foreach ($kv as $a => $kv)
            {
                if (1 === $a)
                {
                    $i = '';
                    $this->push('arrow', ' ⇨ ');
                }

                preg_match(
                    '/^
                    (?:
                         #1
                         (".*)
                         #2
                        |([-\d].*)
                         #3#4     #5      #6#7
                        |((\#\d+)?([\[\{])((\#\d+|\.\.\.)?[\}\]])?)
                         #8
                        |([\]\}]\)?)
                         #9       #10          #11 #12
                        |(Resource(\ \#\d+)\ \((.*)([\)\[]))
                         #13
                        |(\.\.\.(?:"\d+)?)
                         #14#15#16        #17#18
                        |((.*)(\ \#\d+)?\{((\#\d+|\.\.\.)?\})?)
                         #19
                        |(.*)
                    )$
                    /x',
                    $kv,
                    $kv
                );

                if ('' !== $kv[1])
                {
                    if ('"""' === $kv[1]) $this->inString = true;
                    else
                    {
                        $kv[1] = stripcslashes(substr($kv[1], 1, -1));
                        $this->push('string' . $i, $kv[1]);
                    }
                }
                else if ('' !== $kv[2])
                {
                    $this->push('const' . $i, $kv[2]);
                }
                else if ('' !== $kv[3])
                {
                    $this->push('bracket', $kv[3]);
                    if (empty($kv[6]))
                    {
                        $this->indentLevel += 2;
                        $this->indentStack[] = '[' === $kv[5] ? 'array' : 'object';
                    }
                }
                else if ('' !== $kv[8])
                {
                    $this->push('bracket', $kv[8]);
                }
                else if ('' !== $kv[9])
                {
                    $this->push('resource', $kv[9]);
                    if ('[' === $kv[12])
                    {
                        $this->indentLevel += 2;
                        $this->indentStack[] = 'array';
                    }
                }
                else if ('' !== $kv[13])
                {
                    $this->push('truncation', $kv[13]);
                }
                else if ('' !== $kv[14])
                {
                    $this->push('class', $kv[14]);
                    if (empty($kv[17]))
                    {
                        $this->indentLevel += 2;
                        $this->indentStack[] = 'object';
                    }
                }
                else
                {
                    $this->push('const', $kv[19]);
                }
            }
        }

        return $this->tokens;
    }

    protected function push($tag, $data)
    {
        $t = array();

        foreach (explode(' ', $tag) as $tag)
        {
            $t[$tag] = $tag;

            if ('string' === $tag && '' === $data) $t['empty'] = 'empty';
        }

        if (isset($t['string']) || isset($t['const'])) $t['data'] = 'data';
        else $t['punct'] = 'punct';

        if (isset($t['key'], $t['object']))
        {
            $tag = explode(':', $data);
            isset($tag[1]) || array_unshift($tag, '');
            $data = $tag[1];

            if ('' === $tag[0]) $tag = 'public';
            else if ('*' === $tag[0]) $tag = 'protected';
            else
            {
                $t['private-class'] = $tag[0];
                $tag = 'private';
            }

            $t[$tag] = $tag;
        }

        $t[] = $data;

        $this->tokens[] = $t;
    }
}
