<?php
/**
 * S3 file system lib
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die;

define('OBJECT_LOCATION_ERROR', -1);
define('OBJECT_LOCATION_LOCAL', 0);
define('OBJECT_LOCATION_DUPLICATED', 1);
define('OBJECT_LOCATION_REMOTE', 2);

define('OBJECTFS_REPORT_OBJECT_LOCATION', 0);
define('OBJECTFS_REPORT_LOG_SIZE', 1);
define('OBJECTFS_REPORT_MIME_TYPE', 2);

function update_object_record($contentid, $location) {

    $newobject = new \stdClass();
    $newobject->contentid = $contentid;
    $newobject->timeduplicated = time();
    $newobject->location = $location;

    $oldobject = get_record('module_objectfs_objects', 'contentid', $contentid);

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
        set_config($key, $value, 'module_objectfs');
    }
}

function get_objectfs_config() {
    $config = new stdClass;
    $config->enabletasks = 0;
    $config->enablelogging = 0;
    $config->key = '';
    $config->secret = '';
    $config->bucket = '';
    $config->region = 'ap-southeast-2';
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * 24 * 60 * 60;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * 60;
    $config->maxtaskruntime = 60;
    $config->logging = 0;
    $config->preferremote = 0;
    
    $keys = array('enabletasks', 'key', 'secret', 'bucket', 'region', 'sizethreshold', 'minimumage',
        'deletelocal', 'consistencydelay', 'maxtaskruntime', 'logging', 'preferredmode');

    $storedconfig = new stdClass();
    foreach ($keys as $key) {
        $storedconfig->$key = get_config_plugin('module', 'objectfs', $key);
    }

    // Override defaults if set.
    foreach ($storedconfig as $key => $value) {
        if ($value) {
            $config->$key = $value;
        }
    }
    return $config;
}

function module_objectfs_should_tasks_run() {
    $config = get_objectfs_config();
    if (isset($config->enabletasks) && $config->enabletasks) {
        return true;
    }
    return false;
}