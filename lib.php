<?php
/**
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');
require_once(get_config('docroot') . 'artefact/lib.php');
require_once(get_config('docroot') . 'artefact/file/lib.php');

use module_objectfs\object_manipulator\pusher;
use module_objectfs\object_manipulator\puller;
use module_objectfs\object_manipulator\deleter;


class PluginModuleObjectfs {

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
            'url'    => 'module/objectfs/object_status.php',
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

        $defaultconfig = self::get_default_config();

        $config = array();
        $config['generalsettings']            = self::define_general_settings($defaultconfig);
        $config['transfersettings']           = self::define_transfer_settings($defaultconfig);
        $config['objectfssettingsfilesystem'] = self::define_client_selection($defaultconfig);
        $clients = self::get_client_components('client');

        foreach ($clients as $name => $classname) {
            /** @var \module_objectfs\client\object_client $client */
            $client = new $classname($defaultconfig);
            if ($client->get_availability()) {
                $config[$name . 'settings'] = $client->define_settings_form();
            }
        }
        return array(
            'elements' => $config,
        );

    }

    public static function define_client_selection($config) {

        $names = self::get_client_components('file_system');

        $clientlist = array_combine($names, $names);

        return array(
            'type' => 'fieldset',
            'legend' => get_string('settings:storagefilesystemselectionheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => false,
            'elements' => array(
                'filesystem' => array(
                    'title'        => get_string('settings:storagefilesystem', 'module.objectfs'),
                    'description'        => get_string('settings:storagefilesystem_help', 'module.objectfs'),
                    'type'         => 'select',
                    'options'      => $clientlist,
                    'defaultvalue' => $config->filesystem,
                ),
            ),
        );

    }

    private static function get_default_config() {
        // Get default config.
        $defaultconfig = get_objectfs_config();

        if (isset($_POST)) {
            foreach ($_POST as $key => $value) {
                foreach ($defaultconfig as $key1 => $value1) {
                    if ($key == $key1) {
                        $defaultconfig->$key1 = $value;
                    }
                }
            }
        }
        return $defaultconfig;
    }

    private static function check_connection($client) {
        $connection = $client->test_connection();

        if ($connection->success) {
            $connection = '<span class="icon icon-check text-success"></span><span class="bg-success">';
            $connection .= get_string('settings:connectionsuccess', 'module.objectfs');
            $connection .= '</span>';
        } else {
            $connection = '<span class="icon icon-times text-danger"></span><span class="bg-danger">';
            $connection .= get_string('settings:connectionfailure', 'module.objectfs') . '</span>';
        }
        return $connection;
    }

    private static function check_permissions($client) {
        $connection = $client->test_connection();

        if ($connection->success) {
            $permissions = $client->test_permissions();

            $errormsg = '';
            if ($permissions->success) {
                $permissionsmsg = '<span class="icon icon-check text-success"></span><span class="bg-success">';
                $permissionsmsg .= get_string('settings:permissioncheckpassed', 'module.objectfs') . '</span>';
            } else {
                foreach ($permissions->messages as $message) {
                    $errormsg .= $message;
                }
                $permissionsmsg = '<span class="icon icon-times text-danger"></span><span class="bg-danger">';
                $permissionsmsg .= $errormsg . '</span>';
            }

            $permissionsoutput = array('title' => get_string('settings:permissions', 'module.objectfs'),
                'type'  => 'html',
                'value' => $permissionsmsg,
            );
        } else {
            $permissionsoutput = array('title' => get_string('settings:permissions', 'module.objectfs'),
                'type'  => 'html',
                'value' => $connection,
            );
        }

        return $permissionsoutput;
    }

    public static function validate_config_options(Pieform $form, $values) {

        if ($values['sizethreshold'] < 0) {

            $form->set_error('sizethreshold', get_string('validationerror:negative', 'module.objectfs'));
        }
        if ($values['minimumage'] < 0) {

            $form->set_error('minimumage', get_string('validationerror:negative', 'module.objectfs'));
        }
        if ($values['maxtaskruntime'] < 0) {

            $form->set_error('maxtaskruntime', get_string('validationerror:negative', 'module.objectfs'));
        }
        if ($values['consistencydelay'] < 0) {

            $form->set_error('consistencydelay', get_string('validationerror:negative', 'module.objectfs'));
        }
    }

    public static function save_config_options(Pieform $form, $values) {
        // Convert sizethreshold into Bytes.
        $sizethreshold = $values['sizethreshold'] * 1024;
        set_config_plugin('module', 'objectfs', 'sizethreshold', $sizethreshold);
        set_config_plugin('module', 'objectfs', 'minimumage', $values['minimumage']);
        set_config_plugin('module', 'objectfs', 'deletelocal', $values['deletelocal']);
        set_config_plugin('module', 'objectfs', 'enabletasks', $values['enabletasks']);
        set_config_plugin('module', 'objectfs', 'preferexternal', $values['preferexternal']);
        set_config_plugin('module', 'objectfs', 'maxtaskruntime', $values['maxtaskruntime']);
        set_config_plugin('module', 'objectfs', 'consistencydelay', $values['consistencydelay']);
        set_config_plugin('module', 'objectfs', 's3_key', $values['s3_key']);
        set_config_plugin('module', 'objectfs', 's3_secret', $values['s3_secret']);
        set_config_plugin('module', 'objectfs', 's3_bucket', $values['s3_bucket']);
        set_config_plugin('module', 'objectfs', 's3_region', $values['s3_region']);

        set_config_plugin('module', 'objectfs', 'azure_accountname', $values['azure_accountname']);
        set_config_plugin('module', 'objectfs', 'azure_container', $values['azure_container']);
        set_config_plugin('module', 'objectfs', 'azure_sastoken', $values['azure_sastoken']);
        set_config_plugin('module', 'objectfs', 'filesystem', $values['filesystem']);
    }

    public static function postinst($fromversion) {
    }

    // All these default methods need to make some sense, need them to install plugin, some mahara stuff??????????
    public static function menu_items() {
        return array();
    }

    /**
     * Is plugin deprecated - going to be obsolete / removed
     * @return bool
     */
    public static function is_deprecated() {
        return false;
    }

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
                'callfunction' => 'generate_status_report_task',
                'hour'         => '*',
                'minute'       => '*/5',
            ),
        );
    }

    /**
     * Push to S3
     */
    public static function push_objects_to_storage() {
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/pusher.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            \module_objectfs\object_manipulator\manipulator::setup_and_run_object_manipulator('pusher');
        } else {
            log_debug(get_string('not_enabled', 'module.objectfs'));
        }
    }

    /**
     * Pull from S3
     */
    public static function pull_objects_from_storage() {
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/puller.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            \module_objectfs\object_manipulator\manipulator::setup_and_run_object_manipulator('puller');
        } else {
            log_debug(get_string('not_enabled', 'module.objectfs'));
        }
    }

    /**
     * Delete from local
     */
    public static function delete_local_objects() {
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/deleter.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            \module_objectfs\object_manipulator\manipulator::setup_and_run_object_manipulator('deleter');
        } else {
            log_debug(get_string('not_enabled', 'module.objectfs'));
        }
    }

    /**
     * Recover error objects
     */
    public static function recover_error_objects() {
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');
        require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/recoverer.php');

        $config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        if (isset($config->enabletasks) && $config->enabletasks) {
            \module_objectfs\object_manipulator\manipulator::setup_and_run_object_manipulator('recoverer');
        } else {
            log_debug(get_string('not_enabled', 'module.objectfs'));
        }
    }

    /**
     * Generate reports task
     */
    public static function generate_status_report_task() {
        require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report.php');

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

        \module_objectfs\report\objectfs_report::generate_status_report();
    }

    /**
     * @param $defaultconfig
     * @return mixed
     */
    public static function define_general_settings($defaultconfig) {
        $extfsconfig = get_config('externalfilesystem', false);

        if (!$extfsconfig) {
            $extfsconf = '<span class="icon icon-times text-danger"></span><span class="bg-danger">';
            $extfsconf .= get_string('settings:handlernotset', 'module.objectfs') . '</span>';
        } else {

            $extfsconf = '<span class="icon icon-check text-success"></span><span class="bg-success">';
            $extfsconf .= get_string('settings:handlerset', 'module.objectfs') . '</span>';
        }

        $config = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:generalheader', 'module.objectfs'),
            'collapsible' => true,
            'elements' => array(
                'report' => array(
                    'type' => 'html',
                    'value' => '<a href="/module/objectfs/object_status.php" class="objectfs_object_status_link">View object status</a>',
                ),
                'extfsconfig' => array(
                    'title' => get_string('settings:handler', 'module.objectfs'),
                    'type' => 'html',
                    'value' => $extfsconf
                ),
                'enabletasks' => array(
                    'title' => get_string('settings:enabletasks', 'module.objectfs'),
                    'description' => get_string('settings:enabletasks_help', 'module.objectfs'),
                    'type' => 'checkbox',
                    'defaultvalue' => $defaultconfig->enabletasks,
                ),
                'maxtaskruntime' => array(
                    'title' => get_string('settings:maxtaskruntime', 'module.objectfs'),
                    'description' => get_string('settings:maxtaskruntime_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $defaultconfig->maxtaskruntime,
                    // Lets set the maximum run time to one day.
                    'rules' => array('integer' => true, 'minvalue' => 0, 'maxvalue' => (24 * 60 * 60))
                ),
                'preferexternal' => array(
                    'title' => get_string('settings:preferexternal', 'module.objectfs'),
                    'description' => get_string('settings:preferexternal_help', 'module.objectfs'),
                    'type' => 'checkbox',
                    'defaultvalue' => $defaultconfig->preferexternal,
                ),
            ),
        );
        return $config;
    }

    /**
     * @param $defaultconfig
     * @return mixed
     */
    public static function define_transfer_settings($defaultconfig) {
        $config = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:filetransferheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'sizethreshold' => array(
                    'title' => get_string('settings:sizethreshold', 'module.objectfs'),
                    'description' => get_string('settings:sizethreshold_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $defaultconfig->sizethreshold / 1024,
                    // The limit for Amazon S3 is 5GB.
                    'rules' => array('integer' => true, 'minvalue' => 0, 'maxvalue' => 5000000)
                ),
                'minimumage' => array(
                    'title' => get_string('settings:minimumage', 'module.objectfs'),
                    'description' => get_string('settings:minimumage_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $defaultconfig->minimumage,
                    'rules' => array('integer' => true, 'minvalue' => 0)
                ),
                'deletelocal' => array(
                    'title' => get_string('settings:deletelocal', 'module.objectfs'),
                    'description' => get_string('settings:deletelocal_help', 'module.objectfs'),
                    'type' => 'checkbox',
                    'defaultvalue' => $defaultconfig->deletelocal,
                ),
                'consistencydelay' => array(
                    'title' => get_string('settings:consistencydelay', 'module.objectfs'),
                    'description' => get_string('settings:consistencydelay_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $defaultconfig->consistencydelay,
                    'rules' => array('integer' => true, 'minvalue' => 0)
                ),
            ),
        );
        return $config;
    }

    public static function get_client_components($type = 'base') {
        global $CFG;

        $found = array();

        $path = $CFG->docroot . '/module/objectfs/classes/client/*_client.php';

        $clientpaths = glob($path);

        foreach ($clientpaths as $clientpath) {

            $basename = basename($clientpath);

            $clientname = str_replace('_client.php', '', $basename);

            // Ignore the abstract class.
            if ($clientname == 'object') {
                continue;
            }

            switch ($type) {
                case 'file_system':
                    $found[$clientname] = '\\module_objectfs\\' . $clientname . '_file_system';
                    require_once($clientpath);
                    break;
                case 'client':
                    $found[$clientname] = '\\module_objectfs\\client\\' . $clientname . '_client';
                    require_once($clientpath);
                    break;
                case 'base':
                    $found[$clientname] = $clientname;
                    require_once($clientpath);
                    break;
                default:
                    break;
            }
        }

        return $found;
    }
}
