<?php
/**
 * Pulls files from remote storage if they meet the configured criteria.
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

class puller extends manipulator {

    /**
     * Size threshold for pulling files from remote in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * Puller constructor.
     *
     * @param object_client $client object client
     * @param PluginModuleObjectfs $filesystem object file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config) {
        parent::__construct($filesystem, $config);
        $this->sizethreshold = $config->sizethreshold;
    }

    /**
     * Get candidate content ids for pulling.
     * Files that are less or equal to the sizethreshold,
     * and are external.
     *
     * @return array candidate contentids
     */
    public function get_candidate_objects() {
        $sql = 'SELECT af.artefact,
                       MAX(af.size) AS filesize
                  FROM {artefact_file_files} af
             LEFT JOIN {artefact} a ON af.artefact = a.id
             LEFT JOIN {module_objectfs_objects} o ON af.artefact = o.contentid
              GROUP BY af.artefact,
                       af.size,
                       o.location
                HAVING MAX(af.size) <= ?
                       AND (o.location = ?)';

        $params = array($this->sizethreshold, OBJECT_LOCATION_REMOTE);

        $starttime = time();
        $files = get_records_sql_array($sql, $params);
        $duration = time() - $starttime;
        $count = count($files);

        $logstring = "File puller query took $duration seconds to find $count files \n";
        log_debug($logstring);

        if ($files == false ) {
            $files = array();
        }

        return $files;
    }

    /**
     * Pulles files from S3 file system to local.
     *
     * @param  array $candidateids content ids to pull
     */
    public function execute($files) {
        $starttime = time();
        $objectcount = 0;
        $totalfilesize = 0;

        foreach ($files as $file) {
            if (time() >= $this->finishtime) {
                break;
            }

            $success = $this->filesystem->copy_object_from_remote_to_local_by_id($file->artefact);

            if ($success) {
                $location = OBJECT_LOCATION_DUPLICATED;
            } else {
                $location = $this->filesystem->get_actual_object_location_by_id($file->artefact);
            }

            update_object_record($file->artefact, $location);

            $objectcount++;
            $totalfilesize += $file->filesize;
        }

        $duration = time() - $starttime;
        $totalfilesize = display_size($totalfilesize);
        $logstring = "File puller processed $objectcount files, total size: $totalfilesize in $duration seconds \n";
        log_debug($logstring);
    }
}