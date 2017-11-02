<?php
/**
 * ObjectFS report abstract class.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

defined('INTERNAL') || die();

abstract class objectfs_report_builder {

    abstract public function build_report();

    public static function save_report_to_database(objectfs_report $report) {

        $reporttype = $report->get_report_type();
        $reportrows = $report->get_rows();

        // Remove old records.
        delete_records('module_objectfs_reports', 'reporttype', $reporttype));

        // Add report type to each row.
        foreach ($reportrows as $row) {
            $row->reporttype = $reporttype;
            // We dont use insert_records because of 26 compatibility.
            insert_record('module_objectfs_reports', $row);
        }
    }

    public static function load_report_from_database($reporttype) {

        $rows = get_records_array('module_objectfs_reports', 'reporttype', $reporttype);
        $report = new objectfs_report($reporttype);

        if (!empty($rows)) {
            $report->add_rows($rows);
        }

        return $report;
    }
}
