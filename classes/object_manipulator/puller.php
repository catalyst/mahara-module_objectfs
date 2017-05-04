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
     * @param objectfs_file_system $filesystem object file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);
        $this->sizethreshold = $config->sizethreshold;

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->get('remotefilesystem')->set_logger($this->logger);
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

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        $totalobjectsfound = count($objects);

        $this->logger->log_object_query('get_pull_candidates', $totalobjectsfound);

        if ($objects == false) {
            $objects = array();
        }

        return $objects;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->get('remotefilesystem')->copy_object_from_remote_to_local($this->filesystem);
        return $newlocation;
    }

}