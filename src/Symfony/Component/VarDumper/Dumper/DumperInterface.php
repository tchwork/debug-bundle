<?php

namespace Patchwork\Dumper\Dumper;

use Patchwork\Dumper\Collector\Data;

interface DumperInterface
{
    public function dump(Data $data);
    public function dumpStart();
    public function dumpEnd();
    public function dumpScalar(Cursor $cursor, $type, $value);
    public function dumpString(Cursor $cursor, $str, $bin, $cut);
    public function enterArray(Cursor $cursor, $count, $indexed, $hasChild);
    public function leaveArray(Cursor $cursor, $count, $indexed, $hasChild, $cut);
    public function enterObject(Cursor $cursor, $class, $hasChild);
    public function leaveObject(Cursor $cursor, $class, $hasChild, $cut);
    public function enterResource(Cursor $cursor, $res, $hasChild);
    public function leaveResource(Cursor $cursor, $res, $hasChild, $cut);
}
