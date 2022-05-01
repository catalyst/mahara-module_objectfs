<?php
/**
 * Recovers objects that are in the error state if it can.
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

class recoverer extends manipulator {

    /**
     * recoverer constructor.
     *
     * @param s3_client $client S3 client
     * @param object_file_system $filesystem S3 file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);

        $this->logger = $logger;
        // Inject our logger into the filesystem.
        $this->filesystem->set_logger($this->logger);
    }

    /**
     * Get candidate content hashes for cleaning.
     * Files that are past the consistancy delay
     * and are in location duplicated.
     *
     * @return array candidate contenthashes
     */
    public function get_candidate_objects() {

        $sql = 'SELECT af.artefact,
                       a.artefacttype,
                       MAX(af.size) AS filesize
                  FROM artefact_file_files af
             LEFT JOIN artefact a ON af.artefact = a.id
                  JOIN module_objectfs_objects o ON af.artefact = o.contentid
                 WHERE o.location = ?
              GROUP BY af.artefact,
                       a.artefacttype,
                       af.size,
                       o.location';

        $params = array(OBJECT_LOCATION_ERROR);

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        // If there are no results, false is returned.
        if ($objects === false) {
            $totalobjectsfound = 0;
        } else {
            $totalobjectsfound = count($objects);
        }

        $this->logger->log_object_query('get_recover_candidates', $totalobjectsfound);
        return $objects;
    }

    protected function manipulate_object($objectrecord, $fileartefact) {
        $newlocation = $this->filesystem->get_object_location($fileartefact);
        return $newlocation;
    }

}
