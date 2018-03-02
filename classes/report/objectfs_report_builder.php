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

    public function get_report_type() {
        return static::$reporttype;
    }

    public function build_and_save_report() {

        $reportrows = $this->build_report();
        if (!$reportrows) return;

        // Remove old records.
        delete_records('module_objectfs_reports', 'reporttype', static::$reporttype);

        // Add report type to each row.
        foreach ($reportrows as $row) {
            $row->reporttype = static::$reporttype;
            // We dont use insert_records because of 26 compatibility.
            insert_record('module_objectfs_reports', $row);
        }
    }

    public function load_report_from_database() {

        return get_records_array('module_objectfs_reports', 'reporttype', static::$reporttype);
    }

    public function formatted_report() {
        if( $report = $this->load_report_from_database() ) {
            $rows = $this->format_report($report);
	    return $this->augment_barchart($rows);
        }
    }

    protected function format_report($rows) {
	return $rows;
    }

    protected function augment_barchart($rows) {

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

        // Then calculate the percentages for each row.
        foreach ($rows as $row) {
            if (empty($maxobjectcount)) {
                $row->relativeobjectcount = null;
            } else {
                $row->relativeobjectcount = round(100 * $row->objectcount / $maxobjectcount);
            }

            if (empty($maxobjectsum)) {
                $row->relativeobjectsum = null;
            } else {
                $row->relativeobjectsum = round(100 * $row->objectsum / $maxobjectsum);
            }
        }

        return $rows;
    }


}
