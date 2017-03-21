<?php
/**
 * module_objectfs tasks
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'module_objectfs\task\push_objects_to_storage',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'module_objectfs\task\generate_status_report',
        'blocking'  => 0,
        'minute'    => '17',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'module_objectfs\task\delete_local_objects',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
    array(
        'classname' => 'module_objectfs\task\pull_objects_from_storage',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour '     => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*'
    ),
);

