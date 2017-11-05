<?php
/**
 *  Simple CLI file to test pushing files to S3
 *
 * @package     mahara
 * @subpackage  module_objectfs
 * @author      Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

define('CLI', true);
define('INTERNAL', true);

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) .'/init.php');
require_once(get_config('libroot') . 'cli.php');

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');
require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');

use module_objectfs\object_manipulator\manipulator;

$cli = get_cli();

$options = array();

$settings = new \stdClass();
$settings->options = $options;
$settings->info = 'CLI script to push files to S3';

$cli->setup($settings);

$config = get_objectfs_config();

manipulator::setup_and_run_object_manipulator('recoverer');
