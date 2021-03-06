<?php
/**
 * objectfs report class.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

require_once(get_config('docroot') . 'module/objectfs/classes/report/location_report_builder.php');
require_once(get_config('docroot') . 'module/objectfs/classes/report/log_size_report_builder.php');
require_once(get_config('docroot') . 'module/objectfs/classes/report/mime_type_report_builder.php');
require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report_builder.php');

defined('INTERNAL') || die();

class objectfs_report {
    protected $reporttype;
    protected $rows;

    public function __construct($reporttype) {
        $this->reporttype = $reporttype;
        $this->rows = array();
    }

    public function add_row($datakey, $objectcount, $objectsum) {
        $row = new \stdClass();
        $row->datakey = $datakey;
        $row->objectcount = $objectcount;
        $row->objectsum = $objectsum;
        $this->rows[] = $row;
    }

    public function add_rows($rows) {
        foreach ($rows as $row) {
            $this->add_row($row->datakey, $row->objectcount, $row->objectsum);
        }
    }

    public function get_rows() {
        return $this->rows;
    }

    public function get_report_type() {
        return $this->reporttype;
    }

    public static function generate_status_report() {
        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "module_objectfs\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report();

            objectfs_report_builder::save_report_to_database($report);
        }
    }

    public static function get_report_types() {
        $reporttypes = array('location',
                              'log_size',
                              'mime_type');

        return $reporttypes;
    }
}
