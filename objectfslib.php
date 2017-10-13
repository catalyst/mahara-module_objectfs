<?php
/**
 * S3 file system lib
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die;

define('OBJECT_LOCATION_ERROR', -1);
define('OBJECT_LOCATION_LOCAL', 0);
define('OBJECT_LOCATION_DUPLICATED', 1);
define('OBJECT_LOCATION_EXTERNAL', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

function update_object_record($contenthash, $location) {

    $newobject = new \stdClass();
    $newobject->contenthash = $contenthash;
    $newobject->timeduplicated = time();
    $newobject->location = $location;

    $oldobject = get_record('module_objectfs_objects', 'contenthash', $contenthash);

    if ($oldobject) {

        // If location hasn't changed we do not need to update.
        if ($oldobject->location === $newobject->location) {
            return $oldobject;
        }

        // If location change is not to duplicated we do not update timeduplicated.
        if ($newobject->location !== OBJECT_LOCATION_DUPLICATED) {
            $newobject->timeduplicated = $oldobject->timeduplicated;
        }

        $newobject->id = $oldobject->id;

        update_record('module_objectfs_objects', $newobject);
    } else {
        insert_record('module_objectfs_objects', $newobject);
    }

    return $newobject;
}

function set_objectfs_config($config) {
    foreach ($config as $key => $value) {
        set_config($key, $value, 'tool_objectfs');
    }
}

function get_objectfs_config() {
    $config = new stdClass;
    $config->enabletasks = 0;
    $config->enablelogging = 0;
    $config->key = '';
    $config->secret = '';
    $config->bucket = '';
    $config->region = 'us-east-1';
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * 24 * 60 * 60;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * 60;
    $config->maxtaskruntime = 60;
    $config->logging = 0;
    $config->preferexternal = 0;

    $storedconfig = get_config('tool_objectfs');

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        $config->$key = $value;
    }
    return $config;
}

function tool_objectfs_should_tasks_run() {
    $config = get_objectfs_config();
    if (isset($config->enabletasks) && $config->enabletasks) {
        return true;
    }

    return false;
}