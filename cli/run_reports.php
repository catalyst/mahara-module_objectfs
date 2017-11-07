<?php
/**
 *  Simple CLI file to generate reports
 *
 * @package     mahara
 * @subpackage  module_objectfs
 * @author      Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI', true);
define('INTERNAL', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) .'/init.php');
require_once(get_config('libroot') . 'cli.php');
require_once(get_config('docroot') . 'module/objectfs/lib.php');


$cli = get_cli();

$options = array();

$settings = new \stdClass();
$settings->options = $options;
$settings->info = 'CLI script to generate reports for local and external objects';

$cli->setup($settings);

PluginModuleObjectfs::generate_status_report_task();
