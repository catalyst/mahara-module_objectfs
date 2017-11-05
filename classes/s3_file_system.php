<?php
/**
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to impletment get_remote_client.
 *
 * @package    mahara
 * @subpackage module_objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs;

defined('INTERNAL') || die();

use module_objectfs\object_file_system;
use module_objectfs\client\s3_client;

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');
require_once(get_config('docroot') . 'module/objectfs/classes/object_file_system.php');

class s3_file_system extends object_file_system {

    protected function get_external_client($config) {
        $s3client = new s3_client($config);
        return $s3client;
    }
}
