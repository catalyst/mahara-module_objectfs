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

    $sitedata[$key]['objectcount'] = $result->objectcount;
    $sitedata[$key]['objectsum'] = $result->objectsum;

    $totalcount += $result->objectcount;
    $totalsum += $result->objectsum;

}

$sitedata['totalcount'] = $totalcount;
$sitedata['totalsum'] = $totalsum;

$sitedata['name'] = 'Mahara';

$smarty = smarty(array('paginator','js/chartjs/Chart.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');
