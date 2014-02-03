<?php

$dir = dirname(dirname(__FILE__));

require_once $dir . '/class/Patchwork/PHP/Walker.php';
require_once $dir . '/class/Patchwork/PHP/Dumper.php';
require_once $dir . '/class/Patchwork/PHP/Dumper/BaseCaster.php';
require_once $dir . '/class/Patchwork/PHP/Dumper/ExceptionCaster.php';
require_once $dir . '/class/Patchwork/PHP/Dumper/PdoCaster.php';
require_once $dir . '/class/Patchwork/PHP/Dumper/DoctrineCaster.php';
require_once $dir . '/class/Patchwork/PHP/CliDumper.php';
require_once $dir . '/class/Patchwork/PHP/JsonDumper.php';
require_once $dir . '/class/Patchwork/PHP/Logger.php';
require_once $dir . '/class/Patchwork/PHP/ThrowingErrorHandler.php';
require_once $dir . '/class/Patchwork/PHP/InDepthErrorHandler.php';
