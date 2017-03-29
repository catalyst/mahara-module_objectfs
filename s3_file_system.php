<?php
/**
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to impletment get_remote_client.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

global $CFG;

require_once($CFG->docroot . 'module/objectfs/lib.php');
require_once($CFG->docroot . 'module/objectfs/classes/client/s3_client.php');

use module_objectfs\client\s3_client;

class ArtefactTypeFile_s3_file_system extends PluginModuleObjectfs {

    protected function get_remote_client($config) {
        $s3client = new s3_client($config);
        return $s3client;
    }
}
