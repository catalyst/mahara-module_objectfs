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

namespace module_objectfs\report;

define('INTERNAL', 1);
define('STAFF', 1);
define('MENUITEM', 'adminhome/objectfs');

require(dirname(dirname(dirname(__FILE__))).'/init.php');
global $CFG;
require_once($CFG->docroot . '/module/objectfs/classes/report/objectfs_report_builder.php');

use module_objectfs\report;

define('TITLE', get_string('object_status:page', 'module.objectfs'));

$sitedata['location'] = objectfs_report_builder::load_report_from_database(0);
$sitedata['logsize'] = objectfs_report_builder::load_report_from_database(1);
$sitedata['mimetypes'] = objectfs_report_builder::load_report_from_database(2);

$smarty = smarty(array('paginator','js/chartjs/Chart.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');
