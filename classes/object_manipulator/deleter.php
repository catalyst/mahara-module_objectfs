<?php
/**
 * Deletes files that are old enough and are in S3.
 *
 * @package    mahra
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

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
     * Size threshold for pushing files to remote in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * deleter constructor.
     *
     * @param sss_client $client S3 client
     * @param object_file_system $filesystem S3 file system
     * @param object $config objectfs config.
     */
    public function __construct($filesystem, $config, $logger) {
        parent::__construct($filesystem, $config);
        $this->consistencydelay = $config->consistencydelay;
        $this->deletelocal = $config->deletelocal;
        $this->sizethreshold = $config->sizethreshold;
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

        if ($this->deletelocal == 0) {
            log_info("Delete local disabled, not running query \n");
            return array();
        }

        $sql = 'SELECT af.artefact,
                       a.artefacttype,
                       MAX(af.size) AS filesize
                  FROM {artefact_file_files} af
             LEFT JOIN {artefact} a ON af.artefact = a.id
                  JOIN {module_objectfs_objects} o ON af.artefact = o.contentid
                 WHERE o.timeduplicated <= ?
                       AND o.location = ?
              GROUP BY af.artefact,
                       a.artefacttype,
                       af.size,
                       o.location
                 HAVING MAX(af.size) > ?';

        $consistencythreshold = time() - $this->consistencydelay;
        $params = array($consistencythreshold, OBJECT_LOCATION_DUPLICATED, $this->sizethreshold);

        $this->logger->start_timing();
        $objects = get_records_sql_array($sql, $params);
        $this->logger->end_timing();

        // If there are no results, false is returned.
        if ($objects === false) {
            $totalobjectsfound = 0;
        } else {
            $totalobjectsfound = count($objects);
        }

        $this->logger->log_object_query('get_delete_candidates', $totalobjectsfound);

        return $objects;
    }


    protected function manipulate_object($objectrecord, $fileartefact) {
        $newlocation = $this->filesystem->delete_object_from_local($fileartefact, $objectrecord->filesize);
        return $newlocation;
    }

    protected function manipulator_can_execute() {
        if ($this->deletelocal == 0) {
            log_info("Delete local disabled \n");
            return false;
        }

        return true;
    }

}
