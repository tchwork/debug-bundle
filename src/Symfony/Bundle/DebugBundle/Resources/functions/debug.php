<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\DebugBundle\DebugBundle;

if (!function_exists('debug')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function debug($var)
    {
        if (func_num_args() > 1) {
            $var = func_get_args();
        }

        return DebugBundle::debug($var);
    }
}
