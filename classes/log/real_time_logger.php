<?php
/**
 * objectfs null logger class.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\log;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/classes/log/objectfs_logger.php');

class real_time_logger extends objectfs_logger {

    public function log_object_read_action($actionname, $objectpath) {

    }

    public function log_object_move_action($actionname, $objecthash, $initallocation, $finallocation) {

    }

    protected function append_timing_string(&$logstring) {
        $timetaken = $this->get_timing();
        if ($timetaken > 0) {
            $logstring .= "Time taken was: $timetaken seconds. ";
        }

    }

    protected function append_size_string(&$logstring, $objectsize) {
        if ($objectsize > 0) {
            $objectsize = display_size($objectsize);
            $logstring .= "The object's size was $objectsize";
        }
    }

    protected function append_location_change_string(&$logstring, $initiallocation, $finallocation) {
        if ($initiallocation == $finallocation) {
            $logstring .= "The object location did not change from $initiallocation. ";
        } else {
            $logstring .= "The object location changed from $initiallocation to $finallocation. ";
        }
    }

    public function log_object_read($readname, $objectpath, $objectsize = 0) {
        $logstring = "The read action '$readname' was used on object with path $objectpath. ";
        $this->append_timing_string($logstring);
        if ($objectsize > 0) {
            $this->append_size_string($logstring, $objectsize);
        }
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    public function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0) {
        $logstring = "The move action '$movename' was performed on object with hash $objecthash. ";
        $this->append_location_change_string($logstring, $initallocation, $finallocation);
        $this->append_timing_string($logstring);
        $this->append_size_string($logstring, $objectsize);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }

    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        $logstring = "The query action '$queryname' was performed. $objectcount objects were returned";
        $this->append_timing_string($logstring);
        // @codingStandardsIgnoreStart
        error_log($logstring);
        // @codingStandardsIgnoreEnd
    }
}
