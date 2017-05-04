<?php
/**
 * objectfs null logger class.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\log;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/s3_lib.php');
require_once($CFG->docroot . '/module/objectfs/classes/log/objectfs_logger.php');

class null_logger extends objectfs_logger {

    public function log_object_read($readname, $objectpath, $objectsize = 0) {
        return;
    }

    public function log_object_move($movename, $initallocation, $finallocation, $objectid, $objectsize = 0) {
        return;
    }

    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        return;
    }

}
