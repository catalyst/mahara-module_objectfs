<?php
/**
 * Task that pushes files to S3.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\task;

use module_objectfs\object_manipulator\pusher;
use module_objectfs\PluginModuleObjectfs;
use module_objectfs\s3_file_system;


defined('INTERNAL') || die();

require_once( __DIR__ . '/../../lib.php');  // Which lib is this for mahara?
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->docroot . '/artefact/file/lib.php');  // Need to check this for mahara

class push_objects_to_storage extends \core\task\scheduled_task {  // No idea so far how to add scheduled task to mahara

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('push_objects_to_storage_task', 'module_objectfs');
    }

    /**
     * Execute task
     */
    public function execute() {
        $config = get_objectfs_config();

        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new s3_file_system();
            $pusher = new pusher($filesystem, $config);
            $candidateids = $pusher->get_candidate_objects();
            $pusher->execute($candidateids);
        } else {
            log_debug(get_string('not_enabled', 'module_objectfs'));
        }
    }
}


