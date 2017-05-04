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

    public static function generate_status_report() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/classes/report/location_report_builder.php');
        require_once($CFG->docroot . 'module/objectfs/classes/report/log_size_report_builder.php');
        require_once($CFG->docroot . 'module/objectfs/classes/report/mime_type_report_builder.php');

        $reporttypes = self::get_report_types();

        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "module_objectfs\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report();
            objectfs_report_builder::save_report_to_database($report);
        }
    }

    public static function save_report_to_database($sitedata) {
        $reporttype = $sitedata['reporttype'];
        if (isset($sitedata['rows'])) {
            $reportrows = $sitedata['rows'];
        }

        // Remove old records.
        delete_records('module_objectfs_report_data', 'reporttype', $reporttype);

        if (is_array($reportrows)) {
            foreach ($reportrows as $row) {
                insert_record('module_objectfs_report_data', $row);
            }
        }
    }

    public static function load_report_from_database($reporttype) {
        $report = array();
        $rows = get_records_array('module_objectfs_report_data', 'reporttype', $reporttype);

        $location = array('local'      => get_string('object_status:location:local', 'module.objectfs'),
                          'duplicated' => get_string('object_status:location:duplicated', 'module.objectfs'),
                          'remote'     => get_string('object_status:location:remote', 'module.objectfs'),
                          'error'      => get_string('object_status:location:error', 'module.objectfs'),
                          'total'      => get_string('object_status:location:total', 'module.objectfs'));

        if ($rows) {
            foreach ($rows as $key => $value) {
                if ($reporttype == 0) {
                    $report[$key][0] = $location[$value->datakey];
                }
                else {
                    if ($reporttype == 1) {
                        $report[$key][0] = get_size_range_from_logsize($value->datakey);
                    }
                    else {
                        if ($reporttype == 2) {
                            $report[$key][0] = $value->datakey;
                        }
                    }
                }
                $report[$key][1] = $value->objectcount;
                $report[$key][2] = $value->objectsum;
            }
        }

        return $report;
    }

    public static function get_report_types() {
        $reporttypes = array('location',
                             'log_size',
                             'mime_type');
        return $reporttypes;
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

