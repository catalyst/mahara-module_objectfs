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
     * Size threshold for pushing files to remote in bytes.
     *
     * @var int
     */
    private $sizethreshold;

    /**
     * deleter constructor.
     *
     * @param sss_client $client S3 client
     * @param objectfs_file_system $filesystem S3 file system
     * @param object $config sssfs config.
     */
    public function __construct($filesystem, $config) {
        parent::__construct($filesystem, $config);
        $this->consistencydelay = $config->consistencydelay;
        $this->deletelocal = $config->deletelocal;
        $this->sizethreshold = $config->sizethreshold;

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
                       o.location
                 HAVING MAX(f.size) > ?';

        $consistancythrehold = time() - $this->consistencydelay;

        $params = array($consistancythrehold, OBJECT_LOCATION_DUPLICATED, $this->sizethreshold);

        $starttime = time();
        $objects = get_records_sql_array($sql, $params);
        $duration = time() - $starttime;
        $count = count($objects);

        $logstring = "File deleter query took $duration seconds to find $count files \n";
        log_debug($logstring);

        if ($objects == false ) {
            $objects = array();
        }

        return $objects;
    }

    protected function manipulate_object($objectrecord) {
        $newlocation = $this->filesystem->get('remotefilesystem')->delete_object_from_local($this->filesystem);
        return $newlocation;
    }
    protected function manipulator_can_execute() {
        if ($this->deletelocal == 0) {
            log_debug("Delete local disabled \n");
            return false;
        }
        return true;
    }

}