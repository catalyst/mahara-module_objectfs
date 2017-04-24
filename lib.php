<?php
/**
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to implement get_remote_client.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

global $CFG;

require_once($CFG->docroot . 'module/objectfs/s3_lib.php');
require_once($CFG->docroot . 'artefact/lib.php');
require_once($CFG->docroot . 'artefact/file/lib.php');

use module_objectfs\client\s3_client;
use module_objectfs\object_manipulator\pusher;
use module_objectfs\object_manipulator\puller;
use module_objectfs\object_manipulator\deleter;

abstract class PluginModuleObjectfs extends ArtefactTypeFile {

    private $remoteclient;
    private $preferremote;

    /**
     * API-Function get the Plugin ShortName
     *
     * @return string ShortName of the plugin
     */
    public static function get_plugin_display_name() {
        return 'objectfs';
    }

    public static function sanity_check() {
    }

    public static function bootstrap() {
    }

    public static function right_nav_menu_items() {
        return array();
    }

    public static function can_be_disabled() {
        return false;
    }

    public static function is_usable() {
        return true;
    }

    public static function get_event_subscriptions() {
        return array();
    }

    public static function get_activity_types() {
        return array();
    }

    /**
     * API-Function: Add a menu item for site admin users.
     */
    public static function admin_menu_items() {
        if (!is_plugin_active('objectfs', 'module')) {
            return array();
        }
        $items['adminhome/objectfs'] = array(
            'path'   => 'adminhome/objectfs',
            'url'    => 'module/objectfs/objectfs.php',
            'title'  => get_string('object_status:page', 'module.objectfs'),
            'weight' => 40,
        );
        if (defined('MENUITEM') && isset($items[MENUITEM])) {
            $items[MENUITEM]['selected'] = true;
        }
        return $items;
    }

    public static function has_config() {
        return true;
    }

    public static function get_config_options() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/classes/client/s3_client.php');

        $configtemp = get_objectfs_config();

        if (isset($_POST)) {
            foreach ($_POST as $key => $value) {
                foreach ($configtemp as $key1 => $value1) {
                    if ($key == $key1) {
                        $configtemp->$key1 = $value;
                    }
                }
            }
        }

        $client = new s3_client($configtemp);
        $connection = $client->test_connection();

        if ($connection) {
            $connection = get_string('settings:connectionsuccess', 'module.objectfs');
            $permissions = $client->permissions_check();

            $errormsg = '';
            if (!$permissions[AWS_CAN_WRITE_OBJECT]) {
                $errormsg .= get_string('settings:writefailure', 'module.objectfs');
            }

            if (!$permissions[AWS_CAN_READ_OBJECT]) {
                $errormsg .= get_string('settings:readfailure', 'module.objectfs');
            }

            if ($permissions[AWS_CAN_DELETE_OBJECT]) {
                $errormsg .= get_string('settings:deletesuccess', 'module.objectfs');
            }

            if (strlen($errormsg) > 0) {
                $permissionsmsg = $errormsg;
            } else {
                $permissionsmsg = get_string('settings:permissioncheckpassed', 'module.objectfs');
            }
            $permissionsoutput = array('title' => get_string('settings:permissions', 'module.objectfs'),
                                       'type'  => 'html',
                                       'value' => $permissionsmsg,
                );
        } else {
            $connection = get_string('settings:connectionfailure', 'module.objectfs');
            $permissionsoutput = array('title' => get_string('settings:permissions', 'module.objectfs'),
                                       'type'  => 'html',
                                       'value' => $connection,
            );
        }

        $regionoptions = array( 'us-east-1'         => 'us-east-1',
                                'us-east-2'         => 'us-east-2',
                                'us-west-1'         => 'us-west-1',
                                'us-west-2'         => 'us-west-2',
                                'ap-northeast-2'    => 'ap-northeast-2',
                                'ap-southeast-1'    => 'ap-southeast-1',
                                'ap-southeast-2'    => 'ap-southeast-2',
                                'ap-northeast-1'    => 'ap-northeast-1',
                                'eu-central-1'      => 'eu-central-1',
                                'eu-west-1'         => 'eu-west-1');

        $config = array();

        $config['generalsettings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:generalheader', 'module.objectfs'),
            'collapsible' => true,
            'elements' => array(
                'report' => array(
                    'type'         => 'html',
                    'value'        => '<a href="/module/objectfs/objectfs.php">Object status</a>',
                ),
                'enabletasks' => array(
                    'title'        => get_string('settings:enabletasks', 'module.objectfs'),
                    'description'  => get_string('settings:enabletasks_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'enabletasks'),
                ),
                'maxtaskruntime' => array(
                    'title'        => get_string('settings:maxtaskruntime', 'module.objectfs'),
                    'description'  => get_string('settings:maxtaskruntime_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'maxtaskruntime'),
                ),
                'preferremote' => array(
                    'title'        => get_string('settings:preferremote', 'module.objectfs'),
                    'description'  => get_string('settings:preferremote_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'preferremote'),
                ),
            ),
        );

        $config['transfersettings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:filetransferheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'sizethreshold' => array(
                    'title'        => get_string('settings:sizethreshold', 'module.objectfs'),
                    'description'  => get_string('settings:sizethreshold_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'sizethreshold'),
                ),
                'minimumage' => array(
                    'title'        => get_string('settings:minimumage', 'module.objectfs'),
                    'description'  => get_string('settings:minimumage_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'minimumage'),
                ),
                'deletelocal' => array(
                    'title'        => get_string('settings:deletelocal', 'module.objectfs'),
                    'description'  => get_string('settings:deletelocal_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'deletelocal'),
                ),
                'consistencydelay' => array(
                    'title'        => get_string('settings:consistencydelay', 'module.objectfs'),
                    'description'  => get_string('settings:consistencydelay_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'consistencydelay'),
                ),
            ),
        );

        $config['sssfssettings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:filetransferheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'connectiontest' => array(
                    'title'        => get_string('settings:connection', 'module.objectfs'),
                    'type'         => 'html',
                    'value' => $connection,
                ),
                'permissionstest' => $permissionsoutput,
                'key' => array(
                    'title'        => get_string('settings:key', 'module.objectfs'),
                    'description'  => get_string('settings:key_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'key'),
                ),
                'secret' => array(
                    'title'        => get_string('settings:secret', 'module.objectfs'),
                    'description'  => get_string('settings:secret_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'secret'),
                ),
                'bucket' => array(
                    'title'        => get_string('settings:bucket', 'module.objectfs'),
                    'description'  => get_string('settings:bucket_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'bucket'),
                ),
                'region' => array(
                    'title'        => get_string('settings:region', 'module.objectfs'),
                    'description'  => get_string('settings:region_help', 'module.objectfs'),
                    'type'         => 'select',
                    'options'     => $regionoptions,
                    'defaultvalue' => get_config_plugin('module', 'objectfs', 'region'),
                ),
            ),
        );

        return array(
            'elements' => $config,
        );

    }

    public static function validate_config_options($form, $values) {
    }

    public static function save_config_options(Pieform $form, $values) {
        set_config_plugin('module', 'objectfs', 'sizethreshold', $values['sizethreshold']);
        set_config_plugin('module', 'objectfs', 'minimumage', $values['minimumage']);
        set_config_plugin('module', 'objectfs', 'deletelocal', $values['deletelocal']);
        set_config_plugin('module', 'objectfs', 'enabletasks', $values['enabletasks']);
        set_config_plugin('module', 'objectfs', 'preferremote', $values['preferremote']);
        set_config_plugin('module', 'objectfs', 'maxtaskruntime', $values['maxtaskruntime']);
        set_config_plugin('module', 'objectfs', 'consistencydelay', $values['consistencydelay']);
        set_config_plugin('module', 'objectfs', 'key', $values['key']);
        set_config_plugin('module', 'objectfs', 'secret', $values['secret']);
        set_config_plugin('module', 'objectfs', 'bucket', $values['bucket']);
        set_config_plugin('module', 'objectfs', 'region', $values['region']);
    }

    public static function postinst($fromversion) {
        $t = new StdClass;
        $t->name = 'file_s3_file_system';
        $t->plugin = 'file';

        if (!record_exists('artefact_installed_type', 'plugin', $t->name, 'name', $t->plugin)) {
            insert_record('artefact_installed_type', $t);
        }

        $t1 = new StdClass;
        $t1->name = 'file_test_file_system';
        $t1->plugin = 'file';

        if (!record_exists('artefact_installed_type', 'plugin', $t1->name, 'name', $t1->plugin)) {
            insert_record('artefact_installed_type', $t1);
        }

    }

    public static function menu_items() { // All these default methods need to make some sense, need them to install plugin, some mahara stuff??????????
        return array();
    }

    /**
     * Is plugin deprecated - going to be obsolete / removed
     * @return bool
     */
    public static function is_deprecated() {
        return false;
    }

    public function __construct() {
        parent::__construct(-1, array("id" => -1)); // Setup filedir. // This should be fixed properly, need file id before creating filesystem

        $config = get_objectfs_config();

        $this->remoteclient = $this->get_remote_client($config);
        $this->remoteclient->register_stream_wrapper();

        $this->preferremote = $config->preferremote;
    }

    protected abstract function get_remote_client($config);

    /**
     * Scheduled tasks for S3
     */
    public static function get_cron() {

        return array(
            (object)array(
                'callfunction' => 'push_objects_to_storage',
                'hour'         => '*',
                'minute'       => '*/5',
            ),
            (object)array(
                'callfunction' => 'pull_objects_from_storage',
                'hour'         => '*',
                'minute'       => '*/5',
            ),
            (object)array(
                'callfunction' => 'delete_local_objects',
                'hour'         => '*',
                'minute'       => '*/5',
            ),
            (object)array(
                'callfunction' => 'generate_status_report',
                'hour'         => '*',
                'minute'       => '*/5',
            ),
        );
    }

   /**
    * Push to S3
    */
    public static function push_objects_to_storage() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');
        require_once($CFG->docroot . 'module/objectfs/classes/object_manipulator/pusher.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new ArtefactTypeFile_s3_file_system();
            $pusher = new pusher($filesystem, $config);
            $candidateids = $pusher->get_candidate_objects();
            $pusher->execute($candidateids);
        } else {
            log_debug(get_string('not_enabled', 'module_objectfs'));
        }
    }

    /**
     * Pull from S3
     */
    public static function pull_objects_from_storage() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');
        require_once($CFG->docroot . 'module/objectfs/classes/object_manipulator/puller.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new ArtefactTypeFile_s3_file_system();
            $puller = new puller($filesystem, $config);
            $candidateids = $puller->get_candidate_objects();
            $puller->execute($candidateids);
        } else {
            log_debug(get_string('not_enabled', 'module_objectfs'));
        }
    }

    /**
     * Delete from local
     */
    public static function delete_local_objects() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');
        require_once($CFG->docroot . 'module/objectfs/classes/object_manipulator/deleter.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new ArtefactTypeFile_s3_file_system();
            $deleter = new deleter($filesystem, $config);
            $candidateids = $deleter->get_candidate_objects();
            $deleter->execute($candidateids);
        } else {
            log_debug(get_string('not_enabled', 'module_objectfs'));
        }
    }

    /**
     * Generate reports task
     */
    public static function generate_status_report() {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/classes/report/location_report_builder.php');
        require_once($CFG->docroot . 'module/objectfs/classes/report/log_size_report_builder.php');
        require_once($CFG->docroot . 'module/objectfs/classes/report/mime_type_report_builder.php');

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        $locationreport = new \module_objectfs\report\location_report_builder();
        $tmp = $locationreport->build_report();

        $test = array();
        $test['reporttype'] = 0;
        foreach ($tmp as $key => $value) {
            $test['rows'][] = $value;
        }

        $locationreport->save_report_to_database($test);

        $logsizereport = new \module_objectfs\report\log_size_report_builder();
        $tmp = $logsizereport->build_report();

        $test = array();
        $test['reporttype'] = 1;
        foreach ($tmp as $key => $value) {
            $test['rows'][] = $value;
        }

        $logsizereport->save_report_to_database($test);

        $mimetypesreport = new \module_objectfs\report\mime_type_report_builder();
        $tmp = $mimetypesreport->build_report();

        $test = array();
        $test['reporttype'] = 2;
        if (is_array($tmp)) {
            foreach ($tmp as $key => $value) {
                $test['rows'][] = $value;
            }
        }

        $mimetypesreport->save_report_to_database($test);
    }

}
