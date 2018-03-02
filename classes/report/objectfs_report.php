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
    public static function generate_status_report() {
        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilder = self::get_report_builder($reporttype);
            $reportbuilder->build_and_save_report();
        }
    }

    public static function get_status_report() {
        $reporttypes = self::get_report_types();

        $report = array();
        foreach ($reporttypes as $reporttype) {
            $reportbuilder = self::get_report_builder($reporttype);
            $report[$reporttype] = $reportbuilder->formatted_report();
        }
        return $report;
    }

    public static function get_report($reporttype) {
        $reportbuilder = self::get_report_builder($reporttype);
        return $reportbuilder->load_report_from_database();
    }

    private static function get_report_builder($reporttype) {
        $reportbuilderclass = "module_objectfs\\report\\{$reporttype}_report_builder";
        return new $reportbuilderclass();
    }

    public static function get_report_types() {
        return array(
            'location',
            'log_size',
            'mime_type'
        );
    }
}
