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

        if ($this->is_file_readable_locally($contenthash)) {
            $path = $this->get_local_path_from_hash($contenthash);
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

    /**
     * Output the content of the specified stored file.
     *
     * Note, this is different to get_content() as it uses the built-in php
     * readfile function which is more efficient.
     *
     * @param stored_file $file The file to serve.
     * @return void
     */
    public function readfile(\stored_file $file) {
        $path = $this->get_remote_path_from_storedfile($file);

        $this->logger->start_timing();
        $success = readfile_allow_large($path, $file->get_filesize());
        $this->logger->end_timing();

        $this->logger->log_object_read('readfile', $path, $file->get_filesize());

        if (!$success) {
            $file = get_record('artefact_file_files', 'contenthash', $file->get_contenthash());
            $fileartefact = new \ArtefactTypeFile($file->fileid);

            update_object_record($fileartefact, OBJECT_LOCATION_ERROR);
        }
    }

    /**
     * Get the content of the specified stored file.
     *
     * Generally you will probably want to use readfile() to serve content,
     * and where possible you should see if you can use
     * get_content_file_handle and work with the file stream instead.
     *
     * @param stored_file $file The file to retrieve
     * @return string The full file content
     */
    public function get_content(\stored_file $file) {
        if (!$file->get_filesize()) {
            // Directories are empty. Empty files are not worth fetching.
            return '';
        }

        $path = $this->get_remote_path_from_storedfile($file);

        $this->logger->start_timing();
        $contents = file_get_contents($path);
        $this->logger->end_timing();

        $this->logger->log_object_read('file_get_contents', $path, $file->get_filesize());

        if (!$contents) {
            $file = get_record('artefact_file_files', 'contenthash', $file->get_contenthash());
            $fileartefact = new \ArtefactTypeFile($file->fileid);

            update_object_record($fileartefact, OBJECT_LOCATION_ERROR);
        }

        return $contents;
    }

    /**
     * Serve file content using X-Sendfile header.
     * Please make sure that all headers are already sent and the all
     * access control checks passed.
     *
     * @param string $contenthash The content hash of the file to be served
     * @return bool success
     */
    public function xsendfile($contenthash) {
        global $CFG;
        require_once($CFG->libdir . "/xsendfilelib.php");

        $path = $this->get_remote_path_from_hash($contenthash);

        $this->logger->start_timing();
        $success = xsendfile($path);
        $this->logger->end_timing();

        $this->logger->log_object_read('xsendfile', $path);

        if (!$success) {
            $file = get_record('artefact_file_files', 'contenthash', $file->get_contenthash());
            $fileartefact = new \ArtefactTypeFile($file->fileid);

            update_object_record($fileartefact, OBJECT_LOCATION_ERROR);
        }

        return $success;
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param stored_file $file The file to retrieve a handle for
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     */
    public function get_content_file_handle(\stored_file $file, $type = \stored_file::FILE_HANDLE_FOPEN) {
        // Most object repo streams do not support gzopen.
        if ($type == \stored_file::FILE_HANDLE_GZOPEN) {
            $path = $this->get_local_path_from_storedfile($file, true);
        } else {
            $path = $this->get_remote_path_from_storedfile($file);
        }

        $this->logger->start_timing();
        $filehandle = $this->get_object_handle_for_path($path, $type);
        $this->logger->end_timing();

        $this->logger->log_object_read('get_file_handle_for_path', $path, $file->get_filesize());

        if (!$filehandle) {
            $file = get_record('artefact_file_files', 'contenthash', $file->get_contenthash());
            $fileartefact = new \ArtefactTypeFile($file->fileid);

            update_object_record($fileartefact, OBJECT_LOCATION_ERROR);
        }

        return $filehandle;
    }

    /**
     * Return a file handle for the specified path.
     *
     * This abstraction should be used when overriding get_content_file_handle in a new file system.
     *
     * @param string $path The path to the file. This shoudl be any type of path that fopen and gzopen accept.
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource
     * @throws coding_exception When an unexpected type of file handle is requested
     */
    protected function get_object_handle_for_path($path, $type = \stored_file::FILE_HANDLE_FOPEN) {
        switch ($type) {
            case \stored_file::FILE_HANDLE_FOPEN:
                $context = $this->externalclient->get_seekable_stream_context();
                return fopen($path, 'rb', false, $context);
            case \stored_file::FILE_HANDLE_GZOPEN:
                // Binary reading of file in gz format.
                return gzopen($path, 'rb');
            default:
                throw new \coding_exception('Unexpected file handle type');
        }
    }
}
