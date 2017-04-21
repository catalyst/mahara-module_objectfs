<?php
/**
 *
 * @package    mahara
 * @subpackage module
 * @author     Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 *
 */

define('INTERNAL', 1);
define('STAFF', 1);
define('MENUITEM', 'adminhome/objectfs');

require(dirname(dirname(dirname(__FILE__))).'/init.php');

define('TITLE', get_string('object_status:page', 'module.objectfs'));

$locations = array('local'      => OBJECT_LOCATION_LOCAL,
                   'duplicated' => OBJECT_LOCATION_DUPLICATED,
                   'remote'     => OBJECT_LOCATION_REMOTE,
                   'error'      => OBJECT_LOCATION_ERROR);

$totalcount = 0;
$totalsum = 0;

foreach ($locations as $key => $value) {

    if ($value == OBJECT_LOCATION_LOCAL) {
        $localsql = ' or o.location IS NULL';
    } else {
        $localsql = '';
    }

    $sql = 'SELECT count(sub.artefact) as objectcount, SUM(sub.filesize) as objectsum
              FROM (SELECT af.artefact, MAX(af.size) AS filesize
                      FROM artefact_file_files af
                      LEFT JOIN module_objectfs_objects o on af.artefact = o.contentid
                      GROUP BY af.artefact, af.size, o.location
                      HAVING o.location = ?' . $localsql .' ) AS sub 
              WHERE sub.filesize != 0';

    $result = get_record_sql($sql, array($value));

    $sitedata['location'][$key][0] = get_string('object_status:location:' . $key, 'module.objectfs');
    $sitedata['location'][$key][1] = $result->objectcount;
    $sitedata['location'][$key][2] = $result->objectsum;

    $totalcount += $result->objectcount;
    $totalsum += $result->objectsum;

}

$sitedata['totalcount'] = $totalcount;
$sitedata['totalsum'] = $totalsum;


$sql = 'SELECT log as datakey,
               sum(size) as objectsum,
               count(*) as objectcount
          FROM (SELECT DISTINCT artefact, size, floor(log(2,size)) AS log
                    FROM {artefact_file_files}
                    WHERE size != 0) d
      GROUP BY log ORDER BY log';

$stats = get_records_sql_array($sql);

if ($stats) {
    compress_small_log_sizes($stats);
    foreach ($stats as $key => $value) {
        $sizerange = get_size_range_from_logsize($value->datakey); // Turn logsize into a byte range.
        $sitedata['logsize'][$key] = array($sizerange, $value->objectcount, $value->objectsum);
    }
}

$sql = 'SELECT sum(size) as objectsum, filetype as datakey, count(*) as objectcount
        FROM (SELECT size,
                CASE
                    WHEN filetype = \'application/pdf\'                                   THEN \'pdf\'
                    WHEN filetype = \'application/epub+zip\'                              THEN \'epub\'
                    WHEN filetype =    \'application/msword\'                             THEN \'document\'
                    WHEN filetype =    \'application/x-mspublisher\'                      THEN \'document\'
                    WHEN filetype like \'application/vnd.ms-word%\'                       THEN \'document\'
                    WHEN filetype like \'application/vnd.oasis.opendocument.text%\'       THEN \'document\'
                    WHEN filetype like \'application/vnd.openxmlformats-officedocument%\' THEN \'document\'
                    WHEN filetype like \'application/vnd.ms-powerpoint%\'                 THEN \'document\'
                    WHEN filetype = \'application/vnd.oasis.opendocument.presentation\'   THEN \'document\'
                    WHEN filetype =    \'application/vnd.oasis.opendocument.spreadsheet\' THEN \'spreadsheet\'
                    WHEN filetype like \'application/vnd.ms-excel%\'                      THEN \'spreadsheet\'
                    WHEN filetype =    \'application/g-zip\'                              THEN \'archive\'
                    WHEN filetype =    \'application/x-7z-compressed\'                    THEN \'archive\'
                    WHEN filetype =    \'application/x-rar-compressed\'                   THEN \'archive\'
                    WHEN filetype like \'application/%\'                                  THEN \'other\'
                    ELSE         substr(filetype,0,position(\'/\' IN filetype))
                END AS filetype
                FROM {artefact_file_files}
                WHERE filetype IS NOT NULL AND size > 0) stats
        GROUP BY datakey
        ORDER BY
        sum(size) / 1024, datakey;';

$stats = get_records_sql_array($sql);

if ($stats) {
    foreach ($stats as $key => $value) {
        $sitedata['mimetypes'][$key] = array($value->datakey, $value->objectcount, $value->objectsum);
    }
}

$smarty = smarty(array('paginator','js/chartjs/Chart.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');


function compress_small_log_sizes(&$stats) {
    $smallstats = new \stdClass();
    $smallstats->datakey = 'small';
    $smallstats->objectsum = 0;
    $smallstats->objectcount = 0;

    foreach ($stats as $key => $stat) {

        // Logsize of <= 19 means that files are smaller than 1 MB.
        if ($stat->datakey <= 19) {
            $smallstats->objectcount += $stat->objectcount;
            $smallstats->objectsum += $stat->objectsum;
            unset($stats[$key]);
        }

    }
    // Add to the beginning of the array.
    array_unshift($stats, $smallstats);
}

function get_size_range_from_logsize($logsize) {

    // Small logsizes have been compressed.
    if ($logsize == 'small') {
        return '< 1MB';
    }

    $floor = pow(2, $logsize);
    $roof = ($floor * 2);
    $floor = display_size($floor);
    $roof = display_size($roof);
    $sizerange = "$floor - $roof";
    return $sizerange;
}
