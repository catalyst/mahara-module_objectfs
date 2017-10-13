<?php
/**
 * Task that pushes files to S3.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

use tool_objectfs\object_file_system;
use tool_objectfs\s3_file_system;
use tool_objectfs\object_manipulator\manipulator;


defined('MOODLE_INTERNAL') || die();

require_once( __DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/filestorage/file_system.php');

class delete_local_objects extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('delete_local_objects_task', 'tool_objectfs');
    }

    /**
     * Execute task
     */
    public function execute() {
        manipulator::setup_and_run_object_manipulator('\\tool_objectfs\\object_manipulator\\deleter');
    }
}