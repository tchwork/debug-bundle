<?php

namespace Patchwork\Dumper\Dumper;

class Cursor
{
    const HASH_INDEXED = 'indexed-array';
    const HASH_ASSOC = 'associative-array';
    const HASH_OBJECT = 'object';
    const HASH_RESOURCE = 'resource';

    public $depth = 0;
    public $refIndex = false;
    public $refTo = false;
    public $refIsHard = false;
    public $hashType = null;
    public $hashKey = null;
    public $hashIndex = 0;
    public $hashLength = 0;
    public $hashCut = 0;
}
