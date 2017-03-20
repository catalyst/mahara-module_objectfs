<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Task that pushes files to S3.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\tool_objectfs\task;

use module_objectfs\object_manipulator\pusher;
use module_objectfs\object_file_system;  // WHat is this for mahara?
use module_objectfs\s3_file_system;  // WHat is this for mahara?


defined('INTERNAL') || die();

require_once( __DIR__ . '/../../lib.php');  // Which lib is this for mahara?
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->docroot . '/artefact/file/lib.php');  // Need to check this for mahara

class push_objects_to_storage extends \core\task\scheduled_task {  // No idea so far how to add scheduled task to mahara

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('push_objects_to_storage_task', 'tool_objectfs');
    }

    /**
     * Execute task
     */
    public function execute() {
        $config = get_objectfs_config();

        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new s3_file_system();
            $pusher = new pusher($filesystem, $config);
            $candidatehashes = $pusher->get_candidate_objects();
            $pusher->execute($candidatehashes);
        } else {
            log_debug(get_string('not_enabled', 'tool_objectfs'));
        }
    }
}


