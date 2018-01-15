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
require_once(get_config('docroot') . 'module/objectfs/classes/client/s3_client.php');
require_once(get_config('docroot') . 'module/objectfs/classes/client/azure_client.php');
require_once(get_config('docroot') . 'artefact/lib.php');
require_once(get_config('docroot') . 'artefact/file/lib.php');

use module_objectfs\client\s3_client;
use module_objectfs\client\azure_client;
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

      $connection = self::check_s3_connection($defaultconfig);
      $permissionsoutput = self::check_s3_permissions($defaultconfig);

      $azureconnection = self::check_azure_connection($defaultconfig);

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

        $extfsconfig = get_config('externalfilesystem', false);

        if (!$extfsconfig) {
            $extfsconf = '<span class="icon icon-times text-danger"></span><span class="bg-danger">';
            $extfsconf .= get_string('settings:handlernotset', 'module.objectfs') . '</span>';
        } else {

            $extfsconf = '<span class="icon icon-check text-success"></span><span class="bg-success">';
            $extfsconf .= get_string('settings:handlerset', 'module.objectfs') . '</span>';
        }

        $config = array();

        $config['generalsettings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:generalheader', 'module.objectfs'),
            'collapsible' => true,
            'elements' => array(
                'extfsconfig' => array(
                    'title'        => get_string('settings:handler', 'module.objectfs'),
                    'type'         => 'html',
                    'value'        => $extfsconf
                ),
                'report' => array(
                    'type'         => 'html',
                    'value'        => '<a href="/module/objectfs/object_status.php">Object status</a>',
                ),
                'enabletasks' => array(
                    'title'        => get_string('settings:enabletasks', 'module.objectfs'),
                    'description'  => get_string('settings:enabletasks_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => $defaultconfig->enabletasks,
                ),
                'maxtaskruntime' => array(
                    'title'        => get_string('settings:maxtaskruntime', 'module.objectfs'),
                    'description'  => get_string('settings:maxtaskruntime_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->maxtaskruntime,
                    // Lets set the maximum run time to one day.
                    'rules'        => array('integer' => true, 'minvalue' => 0, 'maxvalue' => (24 * 60 * 60))
                ),
                'preferexternal' => array(
                    'title'        => get_string('settings:preferexternal', 'module.objectfs'),
                    'description'  => get_string('settings:preferexternal_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => $defaultconfig->preferexternal,
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
                    'defaultvalue' => $defaultconfig->sizethreshold / 1024,
                    // The limit for Amazon S3 is 5GB.
                    'rules'        => array('integer' => true, 'minvalue' => 0, 'maxvalue' => 5000000)
                ),
                'minimumage' => array(
                    'title'        => get_string('settings:minimumage', 'module.objectfs'),
                    'description'  => get_string('settings:minimumage_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->minimumage,
                    'rules'        => array('integer' => true, 'minvalue' => 0)
                ),
                'deletelocal' => array(
                    'title'        => get_string('settings:deletelocal', 'module.objectfs'),
                    'description'  => get_string('settings:deletelocal_help', 'module.objectfs'),
                    'type'         => 'checkbox',
                    'defaultvalue' => $defaultconfig->deletelocal,
                ),
                'consistencydelay' => array(
                    'title'        => get_string('settings:consistencydelay', 'module.objectfs'),
                    'description'  => get_string('settings:consistencydelay_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->consistencydelay,
                    'rules'        => array('integer' => true, 'minvalue' => 0)
                ),
            ),
        );

        $config['objectfssettingsfilesystem'] = self::define_client_selection($defaultconfig);

        $config['objectfsawss3settings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:awsheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'connectiontest' => array(
                    'title'        => get_string('settings:connection', 'module.objectfs'),
                    'type'         => 'html',
                    'value'        => $connection,
                ),
                'permissionstest' => $permissionsoutput,
                'key' => array(
                    'title'        => get_string('settings:key', 'module.objectfs'),
                    'description'  => get_string('settings:key_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->key,
                ),
                'secret' => array(
                    'title'        => get_string('settings:secret', 'module.objectfs'),
                    'description'  => get_string('settings:secret_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->secret,
                ),
                'bucket' => array(
                    'title'        => get_string('settings:bucket', 'module.objectfs'),
                    'description'  => get_string('settings:bucket_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->bucket,
                ),
                'region' => array(
                    'title'        => get_string('settings:region', 'module.objectfs'),
                    'description'  => get_string('settings:region_help', 'module.objectfs'),
                    'type'         => 'select',
                    'options'      => $regionoptions,
                    'defaultvalue' => $defaultconfig->region,
                ),
            ),
        );

        $config['azuresettings'] = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:azureheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'azureconnectiontest' => array(
                    'title'        => get_string('settings:azureconnection', 'module.objectfs'),
                    'type'         => 'html',
                    'value'        => $azureconnection,
                ),
                'azurepermissionstest' => self::check_azure_permissions($defaultconfig),
                'azure_accountname' => array(
                    'title'        => get_string('settings:azure_accountname', 'module.objectfs'),
                    'description'  => get_string('settings:azure_accountname_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->azure_accountname,
                ),
                'azure_container' => array(
                    'title'        => get_string('settings:azure_container', 'module.objectfs'),
                    'description'  => get_string('settings:azure_container_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->azure_container,
                ),
                'azure_sastoken' => array(
                    'title'        => get_string('settings:azure_sastoken', 'module.objectfs'),
                    'description'  => get_string('settings:azure_sastoken_help', 'module.objectfs'),
                    'type'         => 'text',
                    'defaultvalue' => $defaultconfig->azure_sastoken,
                ),
            ),
        );

        return array(
            'elements' => $config,
        );

    }

    public static function define_client_selection($config) {

        $names = self::module_objectfs_get_client_components('file_system');

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

    public static function module_objectfs_get_client_components($type = 'base') {
        global $CFG;

        $found = [];

        $path = $CFG->docroot . '/module/objectfs/classes/client/*_client.php';

        $clients = glob($path);

        foreach ($clients as $client) {
            $client = str_replace('_client.php', '', $client);
            $basename = basename($client);

            // Ignore the abstract class.
            if ($basename == 'object') {
                continue;
            }

            switch ($type) {
                case 'file_system':
                    $found[$basename] = '\\module_objectfs\\' . $basename . '_file_system';
                    break;
                case 'client':
                    $found[$basename] = '\\module_objectfs\\client\\' . $basename . '_client';
                    break;
                case 'base':
                    $found[$basename] = $basename;
                    break;
                default:
                    break;
            }
        }

        return $found;
    }

    private function get_default_config() {
      // Get default config;
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

    private function check_s3_connection($defaultconfig) {
      $client = new s3_client($defaultconfig);
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

    private function check_s3_permissions($defaultconfig) {
        $client = new s3_client($defaultconfig);
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

    public static function check_azure_connection($defaultconfig) {

        $client = new azure_client($defaultconfig);
        $connection = $client->test_connection();

        if ($connection->success) {
          $connection = '<span class="icon icon-check text-success"></span><span class="bg-success">';
          $connection .= get_string('settings:azureconnectionsuccess', 'module.objectfs');
          $connection .= '</span>';
        } else {
          $connection = '<span class="icon icon-times text-danger"></span><span class="bg-danger">';
          $connection .= get_string('settings:azureconnectionfailure', 'module.objectfs') . '</span>';
        }


        return $connection;
    }

    public static function check_azure_permissions($config) {

        $client = new azure_client($config);
        $connection = $client->test_connection();
        if ($connection->success) {
            // Check permissions if we can connect.
            $permissions = $client->test_permissions();
            if ($permissions->success) {
                $permissionmessage = $permissions->messages[0];
            } else {
                $permissionmessages = array();
                foreach ($permissions->messages as $message) {
                    $permissionmessages[] = $message;
                }
                $permissionmessage = implode('<br />', $permissionmessages);
            }
        }
        else {
          $permissionmessage = 'Connection failed';
        }

        return array('title' => get_string('settings:azurepermissions', 'module.objectfs'),
                                       'type'  => 'html',
                                       'value' => $permissionmessage,
                                     );
    }

    public static function validate_config_options($form, $values) {

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
        set_config_plugin('module', 'objectfs', 'key', $values['key']);
        set_config_plugin('module', 'objectfs', 'secret', $values['secret']);
        set_config_plugin('module', 'objectfs', 'bucket', $values['bucket']);
        set_config_plugin('module', 'objectfs', 'region', $values['region']);

        set_config_plugin('module', 'objectfs', 'azure_accountname', $values['azure_accountname']);
        set_config_plugin('module', 'objectfs', 'azure_container', $values['azure_container']);
        set_config_plugin('module', 'objectfs', 'azure_sastoken', $values['azure_sastoken']);
        set_config_plugin('module', 'objectfs', 'filesystem', $values['filesystem']);

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

}
