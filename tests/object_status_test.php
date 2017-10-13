<?php
/**
 * module_objectfs file status tests.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace module_objectfs\tests;

 defined('MOODLE_INTERNAL') || die();

 use module_objectfs\object_file_system;
 use module_objectfs\report\objectfs_report_builder;
 use module_objectfs\report\objectfs_report;

 require_once(__DIR__ . '/classes/test_client.php');
 require_once(__DIR__ . '/module_objectfs_testcase.php');

class object_status_testcase extends module_objectfs_testcase {

    public function test_report_builders () {
        $reporttypes = objectfs_report::get_report_types();
        foreach ($reporttypes as $reporttype) {
            $reportbuilderclass = "module_objectfs\\report\\{$reporttype}_report_builder";
            $reportbuilder = new $reportbuilderclass();
            $report = $reportbuilder->build_report();
            objectfs_report_builder::save_report_to_database($report);
        }
    }
}
