<?php
/**
 * Deletes files that are old enough and are in S3.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/lib.php');
require_once($CFG->docroot . 'module/objectfs/classes/object_manipulator/manipulator.php');

use Aws\S3\Exception\S3Exception;

class deleter extends manipulator {

    /**
     * How long file must exist after
     * duplication before it can be deleted.
     *
     * @var int
     */
    private $consistencydelay;

    /**
     * Whether to delete local files
     * once they are in remote.
     *
     * @var bool
     */
    private $deletelocal;

    /**
     * deleter constructor.
     *
     * @param sss_client $client S3 client
     * @param PluginModuleObjectfs $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($filesystem, $config) {
        parent::__construct($filesystem, $config);
//        $this->consistencydelay = $config->consistencydelay;
//        $this->deletelocal = $config->deletelocal;
        $this->deletelocal = 1;
    }

    /**
     * Get candidate content ids for cleaning.
     * Files that are past the consistency delay
     * and are in location duplicated.
     *
     * @return array candidate contentids
     */
    public function get_candidate_objects() {

        if ($this->deletelocal == 0) {
            log_debug("Delete local disabled, not running query \n");
            return array();
        }

        $sql = 'SELECT af.artefact,
                       MAX(af.size) AS filesize
                  FROM {artefact_file_files} af
             LEFT JOIN {module_objectfs_objects} o ON af.artefact = o.contentid
                 WHERE o.timeduplicated <= ?
                       AND o.location = ?
              GROUP BY af.artefact,
                       af.size,
                       o.location';

        $consistancythrehold = time() - $this->consistencydelay;
        $params = array($consistancythrehold, OBJECT_LOCATION_DUPLICATED);

        $starttime = time();
        $files = get_records_sql_array($sql, $params);
        $duration = time() - $starttime;
        $count = count($files);

        $logstring = "File deleter query took $duration seconds to find $count files \n";
        log_debug($logstring);

        if ($files == false ) {
            $files = array();
        }

        return $files;
    }

    /**
     * Cleans local file system of candidate id files.
     *
     * @param  array $candidateids content ids to delete
     */
    public function execute($files) {

        $starttime = time();
        $objectcount = 0;
        $totalfilesize = 0;

        if ($this->deletelocal == 0) {
            log_debug("Delete local disabled, not deleting \n");
            return;
        }

        foreach ($files as $file) {
            if (time() >= $this->finishtime) {
//                break;
            }

            $success = $this->filesystem->delete_object_from_local_by_id($file->artefact);

            if ($success) {
                $location = OBJECT_LOCATION_REMOTE;
            } else {
                $location = $this->filesystem->get_actual_object_location_by_id($file->artefact);
            }

            update_object_record($file->artefact, $location);

            $objectcount++;
            $totalfilesize += $file->filesize;
        }

        $duration = time() - $starttime;

        $totalfilesize = display_size($totalfilesize);
        $logstring = "File deleter processed $objectcount files, total size: $totalfilesize in $duration seconds \n";
        log_debug($logstring);
    }
}