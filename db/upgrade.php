<?php
/**
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

function xmldb_module_objectfs_upgrade($oldversion) {



    if ($oldversion < 2017030304) {
        // Set existing installs to default to S3 filesystem.
        set_config_plugin('module', 'objectfs', 'filesystem', '\module_objectfs\s3_file_system');

        // Rename S3 config keys to include a prefix.
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_key', 'key', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_secret', 'secret', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_bucket', 'bucket', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_region', 'region', 'objectfs'));
    }

    return true;
}
