<?php
/**
 * sss report abstract class.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

defined('INTERNAL') || die();

abstract class objectfs_report_builder {

    abstract public function build_report();

    public static function save_report_to_database($sitedata) {
        $reporttype = $sitedata['reporttype'];
        $reportrows = $sitedata['rows'];

        // Remove old records.
        delete_records('module_objectfs_report_data', 'reporttype', $reporttype);
        insert_record('module_objectfs_report_data', $reportrows);
    }

    public static function load_report_from_database($reporttype) {
        $rows = get_records_array('module_objectfs_report_data', 'reporttype', $reporttype);
//        $report = new objectfs_report($reporttype);
//        $report->add_rows($rows);
        return $report;
    }

}
