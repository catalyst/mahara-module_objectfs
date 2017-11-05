<?php
/**
 * objectfs statistic container class.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\log;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

class objectfs_statistic {

    private $key;
    private $objectcount;
    private $objectsum;

    public function __construct($key) {
        $this->key = $key;
        $this->objectcount = 0;
        $this->objectsum = 0;
    }

    public function get_objectcount() {
        return $this->objectcount;
    }

    public function get_objectsum() {
        return $this->objectsum;
    }

    public function get_key() {
        return $this->key;
    }

    public function add_statistic(objectfs_statistic $statistic) {
        $this->objectcount += $statistic->get_objectcount();
        $this->objectsum += $statistic->get_objectsum();
    }

    public function add_object_data($objectcount, $objectsum) {
        $this->objectcount += $objectcount;
        $this->objectsum += $objectsum;
    }
}
