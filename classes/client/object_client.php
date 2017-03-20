<?php
/**
 * Object client interface.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\client;

defined('INTERNAL') || die();

/*interface object_client {
    public function __construct($config);
    public function register_stream_wrapper();
    public function get_remote_md5_from_id($contentid);
    public function get_remote_fullpath_from_id($contentid);
    public function verify_remote_object($contentid, $localpath);
    public function test_connection();
    public function permissions_check();
}*/
interface object_client {
    public function __construct();
}
