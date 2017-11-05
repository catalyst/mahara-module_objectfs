<?php
/**
 * objectfs logger abstract class.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\log;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

abstract class objectfs_logger {
    protected $timestart;
    protected $timeend;

    public function __construct() {
        $this->timestart = 0;
        $this->timeend = 0;
    }

    public function start_timing() {
        $this->timestart = microtime(true);
        return $this->timestart;
    }

    public function end_timing() {
        $this->timeend = microtime(true);
        return $this->timeend;
    }

    protected function get_timing() {
        return $this->timeend - $this->timestart;
    }

    public abstract function log_object_read($readname, $objectpath, $objectsize = 0);
    public abstract function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0);
    public abstract function log_object_query($queryname, $objectcount, $objectsum = 0);
}
