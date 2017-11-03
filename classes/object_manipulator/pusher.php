<?php
/**
 * Pushes files to remote storage if they meet the configured criterea.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once($CFG->docroot . 'module/objectfs/objectfslib.php');

use Aws\S3\Exception\S3Exception;

class pusher extends manipulator {

    /**
     * Size threshold for pushing files to remote in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * Minimum age of a file to be pushed to remote in seconds.
     *
     * @var int
     */
    private $minimumage;

    /**
     * Pusher constructor.
     *
     * @param object_client $client remote object client
     * @param object_file_system $filesystem objectfs file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);
        $this->sizethreshold = $config->sizethreshold;
        $this->minimumage = $config->minimumage;

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    /**
     * Get candidate content hashes for pushing.
     * Files that are bigger than the sizethreshold,
     * less than 5GB (S3 upload max),
     * older than the minimum age
     * and have no location / are in local.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_objects() {

        $sql = 'SELECT af.artefact,
                       MAX(af.size) AS filesize,
                       a.title,
                       o.contenthash
                  FROM artefact_file_files af
             LEFT JOIN artefact a ON af.artefact = a.id
             LEFT JOIN module_objectfs_objects o ON af.artefact = o.contentid
              GROUP BY af.artefact,
                       af.size,
                       o.location,
                       a.title,
                       o.contenthash
                HAVING MIN(a.ctime) <= ?
                       AND MAX(af.size) > ?
                       AND MAX(af.size) < 5000000000
                       AND (o.location IS NULL OR o.location = ?)';

        $maxcreated = time() - $this->minimumage;
        $maxcreatedtimestamp = date('Y-m-d G:H:s', $maxcreated);

        $params = array($maxcreatedtimestamp, $this->sizethreshold, OBJECT_LOCATION_LOCAL);

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        $totalobjectsfound = count($objects);

        $this->logger->log_object_query('get_push_candidates', $totalobjectsfound);

        return $objects;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->copy_object_from_local_to_external_by_hash($objectrecord->contenthash, $objectrecord->filesize);
        return $newlocation;
    }

}


