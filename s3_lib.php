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

    $logrecord = new \stdClass();
    $logrecord->contentid = $contentid;
    $logrecord->timeduplicated = time();
    $logrecord->location = $location;

    $existing = get_record('module_objectfs_objects', 'contentid', $contentid);

    if ($existing) {
        $logrecord->id = $existing->id;
        update_record('module_objectfs_objects', $logrecord);
    } else {
        insert_record('module_objectfs_objects', $logrecord);
    }
}

function set_objectfs_config($config) {
    foreach ($config as $key => $value) {
        set_config($key, $value, 'module_objectfs');
    }
}

function get_objectfs_config() {
    $config = new stdClass;
    $config->enabletasks = 1; // hard coded!!!!!!!!!!!!!!
    $config->key = 'AKIAIRPBEPF7TC7CLZXQ'; // hard coded!!!!!!!!!!!!!!
    $config->secret = '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'; // hard coded!!!!!!!!!!!!!!
    $config->bucket = 'testmahara'; // hard coded!!!!!!!!!!!!!!
    $config->region = 'ap-southeast-2'; // hard coded!!!!!!!!!!!!!!default should be different?
    $config->sizethreshold = 1024 * 10;
    $config->minimumage = 7 * 24 * 60 * 60;
    $config->deletelocal = 0;
    $config->consistencydelay = 10 * 60;
    $config->maxtaskruntime = 60;
    $config->logging = 0;
    $config->preferremote = 0;

    $storedconfig = get_config('module_objectfs'); // Needs to be adjusted to mahara

    // Override defaults if set.
//    foreach ($storedconfig as $key => $value) {
//        $config->$key = $value;
//    }
    return $config;
}