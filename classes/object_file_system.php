<?php
/**
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to impletment get_remote_client.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');
require_once(get_config('docroot') . 'module/objectfs/classes/mahara_external_filesystem.php');
require_once(get_config('docroot') . 'module/objectfs/classes/log/aggregate_logger.php');
require_once(get_config('docroot') . 'module/objectfs/classes/log/null_logger.php');
require_once(get_config('docroot') . 'module/objectfs/classes/log/real_time_logger.php');
require_once(get_config('docroot') . 'artefact/lib.php');
require_once(get_config('docroot') . 'artefact/file/lib.php');

abstract class object_file_system {

    private $externalclient;
    private $preferexternal;
    private $logger;

    public function __construct() {
        global $CFG;

        $config = get_objectfs_config();

        $this->externalclient = $this->get_external_client($config);
        $this->externalclient->register_stream_wrapper();
        $this->preferexternal = $config->preferexternal;
        $this->filepermissions = $CFG->filepermissions;
        $this->dirpermissions = $CFG->directorypermissions;

        if ($config->enablelogging) {
            $this->logger = new \module_objectfs\log\real_time_logger();
        } else {
            $this->logger = new \module_objectfs\log\null_logger();
        }
    }

    public function set_logger(\module_objectfs\log\objectfs_logger $logger) {
        $this->logger = $logger;
    }

    protected abstract function get_external_client($config);

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * Note: This must return a consistent path for the file's contenthash
     * and the path _will_ be in a standard local format.
     * Streamable paths will not work.
     * A local copy of the file _will_ be fetched if $fetchifnotfound is tree.
     *
     * The $fetchifnotfound allows you to determine the expected path of the file.
     *
     * @param string $contenthash The content hash
     * @param bool $fetchifnotfound Whether to attempt to fetch from the remote path if not found.
     * @return string The full path to the content file
     */
    protected function get_local_path_from_hash($contenthash, $fetchifnotfound = false) {

        $file = get_record('artefact_file_files', 'contenthash', $contenthash);
        $fileartefact = new \ArtefactTypeFile($file->fileid);
        $path = $fileartefact->get_local_path();

        if ($fetchifnotfound && !is_readable($path)) {

            // Try and pull from remote.
            $objectlock = $this->acquire_object_lock($fileartefact);

            // While gaining lock object might have been moved locally so we recheck.
            if ($objectlock && !is_readable($path)) {
                $location = $this->copy_object_from_external_to_local($fileartefact, $fileartefact->get('size'));
                // We want this file to be deleted again later.

                update_object_record($fileartefact, $location);

                $this->release_object_lock($fileartefact);
            }
        }

        return $path;
    }

    protected function get_remote_path($fileartefact) {
        $contenthash = $fileartefact->get('contenthash');
        if ($this->preferexternal) {
            $location = $this->get_object_location($fileartefact);
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_external_path_from_hash($contenthash);
            }
        }

        if ($this->is_file_readable_locally($fileartefact)) {
            $path = $fileartefact->get_local_path();
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_external_path_from_hash($contenthash);
        }

        return $path;
    }

    protected function get_external_path_from_hash($contenthash) {
        return $this->externalclient->get_fullpath_from_hash($contenthash);
    }

    public function is_file_readable_locally($fileartefact) {

        $localpath = $fileartefact->get_local_path();

        return is_readable($localpath);
    }

    public function is_file_readable_externally_by_hash($contenthash) {
        if ($contenthash === hash('sha256', '')) {
            // Files with empty size are either directories or empty.
            // We handle these virtually.
            return true;
        }

        $path = $this->get_external_path_from_hash($contenthash, false);

        // Note - it is not possible to perform a content recovery safely from a hash alone.
        return is_readable($path);
    }

    public function get_object_location($fileartefact) {
        $localreadable = $this->is_file_readable_locally($fileartefact);
        $externalreadable = $this->is_file_readable_externally_by_hash($fileartefact->get('contenthash'));

        if ($localreadable && $externalreadable) {
            return OBJECT_LOCATION_DUPLICATED;
        } else if ($localreadable && !$externalreadable) {
            return OBJECT_LOCATION_LOCAL;
        } else if (!$localreadable && $externalreadable) {
            return OBJECT_LOCATION_EXTERNAL;
        } else {
            // Object is not anywhere - we toggle an error state in the DB.
            update_object_record($fileartefact, OBJECT_LOCATION_ERROR);
            return OBJECT_LOCATION_ERROR;
        }
    }

    // Acquire the obect lock any time you are moving an object between locations.
    public function acquire_object_lock($fileartefact) {

        set_field_select('artefact', 'locked', 1, "locked = 0 AND id = ?", array($fileartefact->get('id')));
    }

    // Release the lock once we are done moving objects between locations.
    public function release_object_lock($fileartefact) {

        set_field_select('artefact', 'locked', 0, "locked = 1 AND id = ?", array($fileartefact->get('id')));
    }

    public function copy_object_from_external_to_local($fileartefact, $objectsize = 0) {
        $contenthash = $fileartefact->get('contenthash');
        $initiallocation = $this->get_object_location($fileartefact);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_EXTERNAL) {

            $localpath = $fileartefact->get_local_path();
            $externalpath = $this->get_external_path_from_hash($contenthash);

            $localdirpath = get_config('dataroot')."/".$fileartefact::get_file_directory($fileartefact->get('fileid'));

            // Folder may not exist yet if pulling a file that came from another environment.
            if (!is_dir($localdirpath)) {
                if (!mkdir($localdirpath, $this->dirpermissions, true)) {
                    // Permission trouble.
                    throw new file_exception('storedfilecannotcreatefiledirs');
                }
            }

            $success = copy($externalpath, $localpath);

            if ($success) {
                chmod($localpath, $this->filepermissions);
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }

        }
        $this->logger->log_object_move('copy_object_from_external_to_local',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function copy_object_from_local_to_external($fileartefact, $objectsize = 0) {
        $contenthash = $fileartefact->get('contenthash');
        $initiallocation = $this->get_object_location($fileartefact);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_LOCAL) {

            $localpath = $fileartefact->get_local_path();
            $externalpath = $this->get_external_path_from_hash($contenthash);

            $success = copy($localpath, $externalpath);

            if ($success) {
                $finallocation = OBJECT_LOCATION_DUPLICATED;
            }
        }

        $this->logger->log_object_move('copy_object_from_local_to_external',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }

    public function verify_external_object($fileartefact) {
        $contenthash = $fileartefact->get('contenthash');
        $localpath = $fileartefact->get_local_path();
        $objectisvalid = $this->externalclient->verify_object($contenthash, $localpath);
        return $objectisvalid;
    }

    public function delete_object_from_local($fileartefact, $objectsize = 0) {
        $contenthash = $fileartefact->get('contenthash');
        $initiallocation = $this->get_object_location($fileartefact);
        $finallocation = $initiallocation;

        if ($initiallocation === OBJECT_LOCATION_DUPLICATED) {
            $localpath = $fileartefact->get_local_path();

            if ($this->verify_external_object($fileartefact)) {
                $success = unlink($localpath);

                if ($success) {
                    $finallocation = OBJECT_LOCATION_EXTERNAL;
                }
            }
        }

        $this->logger->log_object_move('delete_local_object',
                                        $initiallocation,
                                        $finallocation,
                                        $contenthash,
                                        $objectsize);
        return $finallocation;
    }
}
