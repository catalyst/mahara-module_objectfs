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

use \module_objectfs\log\objectfs_statistic;

defined('INTERNAL') || die();

require_once($CFG->docroot . 'module/objectfs/objectfslib.php');

class aggregate_logger extends objectfs_logger {

    private $readstatistics; // 1d array of objectfs_statistics.
    private $movestatistics; // 2d array of objecfs_statistics that is lazily setup.
    private $movement;
    private $querystatistics;

    public function __construct() {
        parent::__construct();
        $this->movestatistics = array(
            OBJECT_LOCATION_ERROR => array(),
            OBJECT_LOCATION_LOCAL => array(),
            OBJECT_LOCATION_DUPLICATED => array(),
            OBJECT_LOCATION_EXTERNAL => array()
        );
        $this->readstatistics = array();
        $this->querystatistics = array();
    }

    public function log_object_read($readname, $objectpath, $objectsize = 0) {
        if (array_key_exists($readname, $this->readstatistics)) {
            $readstat = $this->readstatistics[$readname];
        } else {
            $readstat = new objectfs_statistic($readname);
        }

        $readstat->add_object_data(1, $objectsize);
        $this->readstatistics[$readname] = $readstat;
    }

    public function log_object_move($movename, $initallocation, $finallocation, $objecthash, $objectsize = 0) {
        if (!$this->movement) {
            $this->movement = $movename;
        }

        if (array_key_exists($finallocation, $this->movestatistics[$initallocation])) {
            $movestat = $this->movestatistics[$initallocation][$finallocation];
        } else {
            $movestat = new objectfs_statistic($movename);
        }

        $movestat->add_object_data(1, $objectsize);
        $this->movestatistics[$initallocation][$finallocation] = $movestat;
    }

    public function output_move_statistics() {
        $totaltime = $this->get_timing();
        mtrace("$this->movement. Total time taken: $totaltime seconds. Location change summary:");
        foreach ($this->movestatistics as $iniloc => $finlocarr) {
            foreach ($finlocarr as $finloc => $movestat) {
                $this->output_move_statistic($movestat, $iniloc, $finloc);
            }
        }
    }

    protected function output_move_statistic($movestatistic, $initiallocation, $finallocation) {
        $key = $movestatistic->get_key();
        $objectcount = $movestatistic->get_objectcount();
        $objectsum = $movestatistic->get_objectsum();
        $objectsum = display_size($objectsum);
        $initiallocation = $this->location_to_string($initiallocation);
        $finallocation = $this->location_to_string($finallocation);
        mtrace("$initiallocation -> $finallocation. Objects moved: $objectcount. Total size: $objectsum. ");
    }

    public function location_to_string($location) {
        switch ($location) {
            case OBJECT_LOCATION_ERROR:
                return 'error';
            case OBJECT_LOCATION_LOCAL:
                return 'local';
            case OBJECT_LOCATION_DUPLICATED:
                return 'duplicated';
            case OBJECT_LOCATION_EXTERNAL:
                return 'remote';
            default:
                return $location;
        }
    }

    public function log_object_query($queryname, $objectcount, $objectsum = 0) {
        if (array_key_exists($queryname, $this->querystatistics)) {
            $querystat = $this->readstatistics[$queryname];
        } else {
            $querystat = new objectfs_statistic($queryname);
        }

        $querystat->add_object_data($objectcount, $objectsum);
        $this->querystatistics[$queryname] = $querystat;
    }

}
