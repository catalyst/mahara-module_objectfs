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
require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report_builder.php');

use module_objectfs\report\objectfs_report;
use module_objectfs\report\objectfs_report_builder;

define('MENUITEM', 'adminhome/objectfs');
define('TITLE', get_string('object_status:page', 'module.objectfs'));

$reporttypes = objectfs_report::get_report_types();
$sitedata = array();

foreach ($reporttypes as $reporttype) {

    $report = objectfs_report_builder::load_report_from_database($reporttype);

    switch($reporttype) {
        case 'location':
            $sitedata[$reporttype] = format_location_report($report);
            break;
        case 'log_size':
            $sitedata[$reporttype] = format_logsize_report($report);
            break;
        case 'mime_type':
            $sitedata[$reporttype] = format_mimetype_report($report);
            break;
    }

    $sitedata[$reporttype] = $report->get_rows();
}

$smarty = smarty(array('paginator','js/chartjs/Chart.min.js'));
setpageicon($smarty, 'icon-area-chart');

$smarty->assign('sitedata', $sitedata);

$smarty->display('module:objectfs:objectfs.tpl');

function format_location_report($report) {
    $rows = $report->get_rows();

    if (empty($rows)) {
        return '';
    }

    foreach ($rows as $row) {

        $row->datakey = get_file_location_string($row->datakey); // Turn int location into string.
    }

    return augment_barchart($rows);
}

function format_logsize_report($report) {
    // The Logsize report needs information from the location report
    // in order to build the progress bar.
    $logsizereport = objectfs_report_builder::load_report_from_database('location');
    $logsizerows = $logsizereport->get_rows();

    $rows = $report->get_rows();

    if (empty($rows)) {
        return '';
    }

    foreach ($rows as $row) {
        $row->datakey = get_size_range_from_logsize($row->datakey); // Turn logsize into a byte range.
    }

    return augment_barchart($rows);
}

function format_mimetype_report($report) {

    $rows = $report->get_rows();

	if (empty($rows)) {
		return '';
    }

	return augment_barchart($rows);
}

function get_file_location_string($filelocation) {

    if ($filelocation == 'total') {
        return get_string('object_status:location:total', 'module.objectfs');
    }

    switch ($filelocation){
        case OBJECT_LOCATION_ERROR:
            return get_string('object_status:location:error', 'module.objectfs');
        case OBJECT_LOCATION_LOCAL:
            return get_string('object_status:location:local', 'module.objectfs');
        case OBJECT_LOCATION_DUPLICATED:
            return get_string('object_status:location:duplicated', 'module.objectfs');
        case OBJECT_LOCATION_EXTERNAL:
            return get_string('object_status:location:external', 'module.objectfs');
        default:
            return get_string('object_status:location:unknown', 'module.objectfs');
    }
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

function augment_barchart($rows) {

    $maxobjectcount = 0;
    $maxobjectsum = 0;
    $totalobjectsum = 0;

    // Iterate through and calculate the max first.
    foreach ($rows as $row) {
        
        if ($row->objectcount > $maxobjectcount) {

            $maxobjectcount = $row->objectcount;
        }

        if ($row->objectsum > $maxobjectsum) {

            $maxobjectsum = $row->objectsum;
        }

        $totalobjectsum += $row->objectsum;
    }

    // Fail safe so that the universe stays intact, div by 0 errors yada yada.
    if (empty($maxobjectcount)) {

        $maxobjectcount = 100;
    }
    if (empty($maxobjectsum)) {

        $maxobjectsum = 100;
    }

    // Then calculate the percentages for each row.
    foreach ($rows as $row) {
        $row->relativeobjectcount = round(100 * $row->objectcount / $maxobjectcount);
        $row->relativeobjectsum = round(100 * $row->objectsum / $maxobjectsum);
    }

    return $rows;
}
