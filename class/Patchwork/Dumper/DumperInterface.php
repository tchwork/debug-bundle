<?php

namespace Patchwork\Dumper;

interface DumperInterface
{
    public function dumpScalar(Cursor $cursor, $type, $value);
    public function dumpString(Cursor $cursor, $str, $bin, $cut);
    public function enterArray(Cursor $cursor, $count, $cut, $indexed);
    public function leaveArray(Cursor $cursor, $count, $cut, $indexed);
    public function enterObject(Cursor $cursor, $class, $cut);
    public function leaveObject(Cursor $cursor, $class, $cut);
    public function enterResource(Cursor $cursor, $res, $cut);
    public function leaveResource(Cursor $cursor, $res, $cut);
}
