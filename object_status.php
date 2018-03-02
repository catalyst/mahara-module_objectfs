<?php
/**
 * File status page - stats on where files are b/w local file system and s3
 *
 * @package    mahara
 * @subpackage module_objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('INTERNAL', 1);

require(dirname(dirname(dirname(__FILE__))).'/init.php');
require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report.php');

use module_objectfs\report\objectfs_report;

define('MENUITEM', 'adminhome/objectfs');
define('TITLE', get_string('object_status:page', 'module.objectfs'));

$sitedata = objectfs_report::get_status_report();

$smarty = smarty(array('paginator','js/chartjs/Chart.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');
