<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

if ($hassiteconfig) { // This needs to be adjusted for mahara

    $externalpage = new admin_externalpage('module_objectfs',
                                            get_string('object_status:page', 'module_objectfs'),
                                            new moodle_url('/module/objectfs/object_status.php')); // This needs to be adjusted for mahara

    $ADMIN->add('reports', $externalpage);

    $externalpage = new admin_externalpage('tool_objectfs_settings',
                                            get_string('pluginname', 'tool_objectfs'),
                                            new moodle_url('/admin/tool/objectfs/index.php')); // This needs to be adjusted for mahara

    $ADMIN->add('tools', $externalpage); // This needs to be adjusted for mahara

}