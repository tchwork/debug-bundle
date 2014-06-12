<?php

namespace Patchwork\Dumper\Caster;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResourceCaster
{
    public static function castDba($dba, array $a)
    {
        $list = dba_list();
        $a['file'] = $list[substr((string) $dba, 13)];

        return $a;
    }

    public static function castProcess($process, array $a)
    {
        return proc_get_status($process);
    }

    public static function castStream($stream, array $a)
    {
        return stream_get_meta_data($stream);
    }

    public static function castGd($gd, array $a)
    {
        $a['size'] = imagesx($gd).'x'.imagesy($gd);
        $a['trueColor'] = imageistruecolor($gd);

        return $a;
    }

    public static function castMysqlLink($h, array $a)
    {
        $a['host'] = mysql_get_host_info($h);
        $a['protocol'] = mysql_get_proto_info($h);
        $a['server'] = mysql_get_server_info($h);

        return $a;
    }
}
