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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/init.php');
require_once(get_config('libroot') . 'cli.php');

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');
require_once(get_config('docroot') . 'module/objectfs/classes/client/s3_client.php');
require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/manipulator.php');
require_once(get_config('docroot') . 'module/objectfs/classes/object_manipulator/pusher.php');
require_once(get_config('docroot') . 'module/objectfs/classes/log/objectfs_statistic.php');
require_once(get_config('docroot') . 'artefact/file/lib.php');

$cli = get_cli();

$options = array();

$settings = new \stdClass();
$settings->options = $options;
$settings->info = 'CLI script to push files to S3';

$cli->setup($settings);

$config = get_objectfs_config();

$logger = new \module_objectfs\log\aggregate_logger();
$filesystem = new \module_objectfs\s3_file_system();
$manipulator = new pusher($filesystem, $config, $logger);
$candidatehashes = $manipulator->get_candidate_objects();

$i = 0;

foreach ($candidatehashes as $candidatehash) {

    // We only need 10 entries for testing
    if ($i > 10) {
        exit(0);
    }

    // We're only interested in files.
    if ($candidatehash->artefacttype != 'file') {
        continue;
    }

    // Prepare the file artefact.
    $fileartefact = new \ArtefactTypeFile($candidatehash->artefact);

    if (empty($candidatehash->contenthash)) {

        $candidatehash->contenthash = $fileartefact::generate_content_hash($fileartefact->get_local_path());
        $fileartefact->save_content_hash();
    }

    if (!empty($candidatehash->contenthash)) {

        $newlocation = $filesystem->copy_object_from_local_to_external_by_hash($candidatehash->contenthash, $candidatehash->filesize);
        update_object_record($fileartefact, $newlocation);

        log_info(print_r($newlocation));

        // Increment only if we are pushing.
        $i++;
    }
}
