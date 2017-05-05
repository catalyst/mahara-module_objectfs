<?php
/**
 * Recovers objects that are in the error state if it can.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/lib.php');

use Aws\S3\Exception\S3Exception;

class recoverer extends manipulator {

    /**
     * recoverer constructor.
     *
     * @param objectfs_client $client remote object client
     * @param objectfs_file_system $filesystem object file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->get('remotefilesystem')->set_logger($this->logger);
    }

    /**
     * Get candidate content ids for cleaning.
     * Files that are past the consistency delay
     * and are in location duplicated.
     *
     * @return array candidate contentids
     */
    public function get_candidate_objects() {

        $sql = 'SELECT af.artefact,
                       MAX(af.size) AS filesize
                  FROM {artefact_file_files} af
             LEFT JOIN {module_objectfs_objects} o ON af.artefact = o.contentid
                 WHERE o.location = ?
              GROUP BY af.artefact,
                       af.size,
                       o.location';

        $params = array(OBJECT_LOCATION_ERROR);

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        $totalobjectsfound = count($objects);

        $this->logger->log_object_query('get_recover_candidates', $totalobjectsfound);

        if ($objects == false) {
            $objects = array();
        }

        return $objects;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->get('remotefilesystem')->get_actual_object_location($this->filesystem);
        return $newlocation;
    }

}
