<?php
/**
 * Pulls files from remote storage if they meet the configured criterea.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

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
     * @param object_file_system $filesystem object file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);
        $this->sizethreshold = $config->sizethreshold;

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    /**
     * Get candidate content hashes for pulling.
     * Files that are less or equal to the sizethreshold,
     * and are external.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_objects() {

        if (!empty($this->sizethreshold)) {

            $having = 'HAVING MAX(af.size) <= ?
                          AND (o.location = ?)';
            $params = array($this->sizethreshold, OBJECT_LOCATION_EXTERNAL);
        } else {

            $having = 'HAVING (o.location = ?)';
            $params = array(OBJECT_LOCATION_EXTERNAL);
        }

        $sql = "SELECT af.contenthash,
                       MAX(af.size) AS filesize
                  FROM artefact_file_files af
             LEFT JOIN artefact a ON af.artefact = a.id
             LEFT JOIN module_objectfs_objects o ON af.artefact = o.contentid
              GROUP BY af.artefact,
                       af.size,
                       o.location
                $having";

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        $totalobjectsfound = count($objects);

        $this->logger->log_object_query('get_pull_candidates', $totalobjectsfound);

        return $objects;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->copy_object_from_external_to_local_by_hash($objectrecord->contenthash, $objectrecord->filesize);
        return $newlocation;
    }

}


