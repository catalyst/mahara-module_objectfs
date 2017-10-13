<?php
/**
 * Object client interface.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\client;

defined('INTERNAL') || die();

interface object_client {
    public function __construct($config);
    public function register_stream_wrapper();
    public function get_fullpath_from_hash($contenthash);
    public function get_seekable_stream_context();
    public function verify_object($contenthash, $localpath);
    public function test_connection();
    public function test_permissions();
}
