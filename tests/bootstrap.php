<?php

$dir = dirname(dirname(__FILE__));

require_once $dir . '/bootup.dumper.php';
require_once $dir . '/class/Patchwork/Dumper.php';
require_once $dir . '/class/Patchwork/Dumper/Walker.php';
require_once $dir . '/class/Patchwork/Dumper/Dumper.php';
require_once $dir . '/class/Patchwork/Dumper/Caster/BaseCaster.php';
require_once $dir . '/class/Patchwork/Dumper/Caster/ExceptionCaster.php';
require_once $dir . '/class/Patchwork/Dumper/Caster/PdoCaster.php';
require_once $dir . '/class/Patchwork/Dumper/Caster/DoctrineCaster.php';
require_once $dir . '/class/Patchwork/Dumper/CliDumper.php';
require_once $dir . '/class/Patchwork/Dumper/HtmlDumper.php';
require_once $dir . '/class/Patchwork/Dumper/JsonDumper.php';
