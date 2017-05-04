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
 * objectfs null logger class.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\log;

use \module_objectfs\log\objectfs_statistic;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/s3_lib.php');
require_once($CFG->docroot . '/module/objectfs/classes/log/objectfs_logger.php');
require_once($CFG->docroot . '/module/objectfs/classes/log/objectfs_statistic.php');

class aggregate_logger extends objectfs_logger {

    private $readstatistics; // 1d array of objectfs_statistics.
    private $movestatistics; // 2d array of objectfs_statistics that is lazily setup.
    private $movement;
    private $querystatistics;

    public function __construct() {
        parent::__construct();
        $this->movestatistics = array(
            OBJECT_LOCATION_ERROR => array(),
            OBJECT_LOCATION_LOCAL => array(),
            OBJECT_LOCATION_DUPLICATED => array(),
            OBJECT_LOCATION_REMOTE => array()
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

    public function log_object_move($movename, $initallocation, $finallocation, $objectid, $objectsize = 0) {
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
        log_debug("$this->movement. Total time taken: $totaltime seconds. Location change summary:");
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
            case OBJECT_LOCATION_REMOTE:
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
