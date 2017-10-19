<?php
/**
 * File status page - stats on where files are b/w local file system and s3
 *
 * @package    mahara
 * @subpackage module_objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MENUITEM', 'adminhome/objectfs');

require_once(__DIR__ . '/../../../init.php');
global $CFG;
require_once($CFG->docroot . 'module/objectfs/classes/report/objectfs_report.php');
require_once($CFG->docroot . 'module/objectfs/classes/report/objectfs_report_builder.php');

use module_objectfs\report\objectfs_report;
use module_objectfs\report\objectfs_report_builder;

define('TITLE', get_string('object_status:page', 'module.objectfs'));

$reporttypes = objectfs_report::get_report_types();
$sitedata = array();

foreach ($reporttypes as $reporttype) {

    $sitedata[$reporttype] = objectfs_report_builder::load_report_from_database($reporttype);
}

$smarty = smarty(array('paginator', 'js/chartjs/Char.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');
