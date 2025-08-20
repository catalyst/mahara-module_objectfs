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
    global $CFG;

    if ($oldversion < 2017030304) {
        // Set existing installs to default to S3 filesystem.
        set_config_plugin('module', 'objectfs', 'filesystem', '\module_objectfs\s3_file_system');

        // Rename S3 config keys to include a prefix.
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_key', 'key', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_secret', 'secret', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_bucket', 'bucket', 'objectfs'));
        execute_sql("UPDATE {module_config} SET field = ? WHERE field = ? and plugin = ?", array('s3_region', 'region', 'objectfs'));
    }

    if ($oldversion < 2025073100) {
        $dbprefix = (isset($CFG->dbprefix)) ? $CFG->dbprefix : '';

        // Check if DB prefix has been set.
        if (strlen($dbprefix) != 0) {
            $CFG->prefix = '';
            $objectfstable = new XMLDBTable('module_objectfs_objects');
            if (table_exists($objectfstable)) {
                $CFG->prefix = $dbprefix;
                // Check if module_objectfs_objects with DB prefix exists else create the table.
                $table = new XMLDBTable('module_objectfs_objects');
                if (table_exists($table)) {
                    // Delete all records.
                    execute_sql("DELETE FROM {module_objectfs_objects}");
                } else {
                    $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                    $table->addFieldInfo('contentid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
                    $table->addFieldInfo('contenthash', XMLDB_TYPE_CHAR, 64, null, null);
                    $table->addFieldInfo('timeduplicated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
                    $table->addFieldInfo('location', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL);
                    $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
                    create_table($table);
                }
                // Insert records in the new table.
                execute_sql("INSERT INTO {module_objectfs_objects} (contentid, contenthash, timeduplicated, location)
                    SELECT contentid, contenthash, timeduplicated, location FROM module_objectfs_objects");

                // Ensure that redundant tables are removed.
                $CFG->prefix = '';
                $table = new XMLDBTable('module_objectfs_objects');
                drop_table($table);
            }

            $CFG->prefix = '';
            $reportstable = new XMLDBTable('module_objectfs_reports');
            if (table_exists($reportstable)) {
                $CFG->prefix = $dbprefix;
                // Check if module_objectfs_reports with DB prefix exists.
                $table = new XMLDBTable('module_objectfs_reports');
                if (table_exists($table)) {
                    // Delete all records.
                    execute_sql("DELETE FROM {module_objectfs_reports}");
                } else {
                    $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                    $table->addFieldInfo('reporttype', XMLDB_TYPE_CHAR, 15, null, XMLDB_NOTNULL);
                    $table->addFieldInfo('datakey', XMLDB_TYPE_CHAR, 15, null, XMLDB_NOTNULL);
                    $table->addFieldInfo('objectcount', XMLDB_TYPE_INTEGER, 15, null, XMLDB_NOTNULL);
                    $table->addFieldInfo('objectsum', XMLDB_TYPE_INTEGER, 15, null, XMLDB_NOTNULL);
                    $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
                    create_table($table);
                }
                // Insert records in the new table.
                execute_sql("INSERT INTO {module_objectfs_reports} (reporttype, datakey, objectcount, objectsum)
                    SELECT reporttype, datakey, objectcount, objectsum FROM module_objectfs_reports");

                // Ensure that redundant tables are removed.
                $CFG->prefix = '';
                $table = new XMLDBTable('module_objectfs_reports');
                drop_table($table);
            }
            // Reset CFG->prefix
            $CFG->prefix = $dbprefix;
        }
    }

    return true;
}
