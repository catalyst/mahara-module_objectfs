<?php
/* Logs manipulator actions
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

class logger {

    private $action; // Which action to log.
    private $timestart;
    private $timeend;
    private $totalfilesize;
    private $totalfilecount;

    public function __construct() {
        $this->totalfilecount = 0;
        $this->totalfilesize = 0;
    }

    public function start_timing() {
        $this->timestart = time();
    }

    public function end_timing() {
        $this->timeend = time();
    }

    public function set_action($action) {
        $this->action = $action;
    }

    public function add_object_manipulation($filesize) {
        $this->totalfilesize += $filesize;
        $this->totalfilecount++;
    }

    public function log_object_manipulation() {
        $duration = $this->timestart - $this->timeend;
        $totalfilesize = display_size($this->totalfilesize);
        $logstring = "Objectsfs $this->action manipulator took $duration seconds to $this->action $this->totalfilecount objects. ";
        $logstring .= "Total size: $totalfilesize Total time: $duration seconds";
        log_info($logstring);
    }

    public function log_object_manipulation_query($totalobjectsfound) {
        $duration = $this->timeend - $this->timestart;
        $logstring = "Objectsfs $this->action manipulator took $duration seconds to find $totalobjectsfound potential $this->action objects. ";
        $logstring .= "Total time: $duration seconds";
        log_info($logstring);
    }
}
