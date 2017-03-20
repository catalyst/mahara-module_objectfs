<?php
/**
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

function xmldb_module_objectfs_upgrade($oldversion) {

    if ($oldversion < 2017030300) {

        $table = new XMLDBTable('artefact_file_objectfs_report_data');
        $dbman->rename_table($table, 'artefact_file_objectfs_reports');  // WHaT is this in mahara???

        $table = new XMLDBTable('artefact_file_objectfs_reports');

        // Changing type of field reporttype on table tool_objectfs_reports to char.
        $table->addFieldInfo('reporttype', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of type for field reporttype.
        change_field_type($table, $field);

        upgrade_plugin_savepoint(true, 2017030300, 'error', 'objectfs'); // Not sure what it is for mahara?
    }

    return true;
}