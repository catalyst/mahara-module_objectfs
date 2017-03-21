<?php
/**
 * object_file_system abstract class.
 *
 * Remote object storage providers extent this class.
 * At minimum you need to implement get_remote_client.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//namespace module_objectfs;

defined('INTERNAL') || die();

global $CFG;

require_once($CFG->docroot . 'module/objectfs/s3_lib.php');
require_once($CFG->docroot . 'artefact/lib.php');
require_once($CFG->docroot . 'artefact/file/lib.php');

require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');


abstract class PluginModuleObjectfs extends ArtefactTypeFile {

    private $remoteclient;
    private $preferremote;

    /**
     * API-Function get the Plugin ShortName
     *
     * @return string ShortName of the plugin
     */
    public static function get_plugin_display_name() {
        return 'objectfs';
    }

    public static function sanity_check() {
    }

    public static function bootstrap() {
    }

    public static function right_nav_menu_items() {
        return array();
    }

    public function __construct() {
        parent::__construct(); // Setup filedir.

        $config = get_objectfs_config();

        $this->remoteclient = $this->get_remote_client($config);
        $this->remoteclient->register_stream_wrapper();

        $this->preferremote = $config->preferremote;
    }

    protected abstract function get_remote_client($config);

    protected function get_object_path_from_storedfile($file) {
        if ($this->preferremote) {
            $location = $this->get_actual_object_location_by_id($file->get('id'));
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_remote_path_from_storedfile($file);
            }
        }

        if ($this->is_file_readable_locally_by_storedfile($file)) {
            $path = $this->get_path();
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_remote_path_from_storedfile($file);
        }

        return $path;
    }

    /**
     * Get the full path for the specified id, including the path to the filedir.
     *
     * Note: This must return a consistent path for the file's contentid
     * and the path _will_ be in a standard local format.
     * Streamable paths will not work.
     * A local copy of the file _will_ be fetched if $fetchifnotfound is tree.
     *
     * The $fetchifnotfound allows you to determine the expected path of the file.
     *
     * @param int $contentid The content id
     * @param bool $fetchifnotfound Whether to attempt to fetch from the remote path if not found.
     * @return string The full path to the content file
     */
    protected function get_local_path_from_id($contentid, $fetchifnotfound = false) {
        $path = parent::get_path();

        if ($fetchifnotfound && !is_readable($path)) {
            $fetched = $this->copy_object_from_remote_to_local_by_id($contentid);

            if ($fetched) {
                // We want this file to be deleted again later.
                update_object_record($contentid, OBJECT_LOCATION_DUPLICATED);
            }
        }

        return $path;
    }

    /**
     * Get a remote filepath for the specified stored file.
     *
     * This is typically either the same as the local filepath, or it is a streamable resource.
     *
     * See https://secure.php.net/manual/en/wrappers.php for further information on valid wrappers.
     *
     * @param ArtefactTypeFile $file The file to serve.
     * @return string full path to pool file with file content
     */
    protected function get_remote_path_from_storedfile(\ArtefactTypeFile $file) {
        return $this->get_remote_path_from_id($file->get('id'));
    }

    /**
     * Get the full path for the specified id, including the path to the filedir.
     *
     * This is typically either the same as the local filepath, or it is a streamable resource.
     *
     * See https://secure.php.net/manual/en/wrappers.php for further information on valid wrappers.
     *
     * @param int $contentid The content id
     * @return string The full path to the content file
     */
    protected function get_remote_path_from_id($contentid) {
        return $this->remoteclient->get_remote_fullpath_from_id($contentid);
    }

    public function get_actual_object_location_by_id($contentid) {
        $this->set('fileid', $contentid);
        $localpath = $this->get_path();
        $remotepath = $this->get_remote_path_from_id($contentid);

        $localreadable = is_readable($localpath);
        $remotereadable = is_readable($remotepath);

        if ($localreadable && $remotereadable) {
            return OBJECT_LOCATION_DUPLICATED;
        } else if ($localreadable && !$remotereadable) {
            return OBJECT_LOCATION_LOCAL;
        } else if (!$localreadable && $remotereadable) {
            return OBJECT_LOCATION_REMOTE;
        } else {
            return OBJECT_LOCATION_ERROR;
        }
    }

    protected function acquire_object_lock($contentid) {
        $timeout = 600; // 10 minutes before giving up.

        $giveuptime = time() + $timeout;
        $lock = false;

        do {
            $lockedalready = get_record('config', 'field', '_cron_lock_module_objectfs_cron_'.$contentid);
            if ($lockedalready) {
                usleep(rand(10000, 250000)); // Sleep between 10 and 250 milliseconds.
            } else {
                insert_record('config', (object) array('field' => '_cron_lock_module_objectfs_cron_'.$contentid, 'value' => time()));
                $lock = true;
            }
            // Try until the giveup time.
        } while (!$lock && time() < $giveuptime);

        return $lock;
    }

    protected function release_object_lock($contentid) {

        delete_records('config', 'field', '_cron_lock_module_objectfs_cron_'.$contentid);

    }

    public function copy_object_from_remote_to_local_by_id($contentid) {
        $location = $this->get_actual_object_location_by_id($contentid);

        // Already duplicated.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            return true;
        }

        if ($location === OBJECT_LOCATION_REMOTE) {

            $localpath = $this->get_local_path_from_id($contentid);
            $remotepath = $this->get_remote_path_from_id($contentid);

            $objectlock = $this->acquire_object_lock($contentid);

            // Lock is still held by something.
            if (!$objectlock) {
                return false;
            }

            // While waiting for lock, file was moved.
            if (is_readable($localpath)) {
                $this->release_object_lock($contentid);
                return true;
            }

            $result = copy($remotepath, $localpath);

            $this->release_object_lock($contentid);

            return $result;
        }
        return false;
    }

    public function copy_object_from_local_to_remote_by_id($contentid) {
        $location = $this->get_actual_object_location_by_id($contentid);

        // Already duplicated.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            return true;
        }

        if ($location === OBJECT_LOCATION_LOCAL) {

            $localpath = $this->get_local_path_from_id($contentid);
            $remotepath = $this->get_remote_path_from_id($contentid);

            $objectlock = $this->acquire_object_lock($contentid); // Dunno for mahara so far

            // Lock is still held by something.
            if (!$objectlock) {
                return false;
            }

            // While waiting for lock, file was moved.
            if (is_readable($remotepath)) {
                $this->release_object_lock($contentid);
                return true;
            }

            $result = copy($localpath, $remotepath);

            $this->release_object_lock($contentid);

            return $result;
        }
        return false;
    }

    public function delete_object_from_local_by_id($contentid) {
        $location = $this->get_actual_object_location_by_id($contentid);

        // Already deleted.
        if ($location === OBJECT_LOCATION_REMOTE) {
            return true;
        }

        // We want to be very sure it is remote if we're deleting objects.
        // There is no going back.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            $localpath = $this->get_local_path_from_id($contentid);
            $objectvalid = $this->remoteclient->verify_remote_object($contentid, $localpath);
            if ($objectvalid) {
                return unlink($localpath);
            }
        }

        return false;
    }

    /**
     * Output the content of the specified stored file.
     *
     * Note, this is different to get_content() as it uses the built-in php
     * readfile function which is more efficient.
     *
     * @param \ArtefactTypeFile $file The file to serve.
     * @return void
     */
    public function readfile(\ArtefactTypeFile $file) {
        $path = $this->get_object_path_from_storedfile($file);
        readfile_allow_large($path, $file->describe_size());
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
    public function get_content(\ArtefactTypeFile $file) {
        if (!$file->describe_size()) {
            // Directories are empty. Empty files are not worth fetching.
            return '';
        }

        $path = $this->get_object_path_from_storedfile($file);
        return file_get_contents($path);
    }

    /**
     * Serve file content using X-Sendfile header.
     * Please make sure that all headers are already sent and the all
     * access control checks passed.
     *
     * @param int $contentid The content id of the file to be served
     * @return bool success
     */
    public function xsendfile($contentid) { // Might need to adjust to mahara, do we have it at all???
        global $CFG;
        require_once($CFG->libdir . "/xsendfilelib.php"); // Might need to adjust to mahara, do we have it at all???

        $path = $this->get_object_path_from_storedfile($file);
        return xsendfile($path);
    }

    /**
     * Returns file handle - read only mode, no writing allowed into pool files!
     *
     * When you want to modify a file, create a new file and delete the old one.
     *
     * @param ArtefactTypeFile $file The file to retrieve a handle for
     * @param int $type Type of file handle (FILE_HANDLE_xx constant)
     * @return resource file handle
     */
    public function get_content_file_handle(\ArtefactTypeFile $file, $type = \ArtefactTypeFile::FILE_HANDLE_FOPEN) {
        // Most object repo streams do not support gzopen.
        if ($type == \ArtefactTypeFile::FILE_HANDLE_GZOPEN) {
            $path = $this->get_local_path_from_storedfile($file, true);
        } else {
            $path = $this->get_object_path_from_storedfile($file);
        }
        return self::get_file_handle_for_path($path, $type);
    }

    /**
     * Marks pool file as candidate for deleting.
     *
     * We adjust this method from the parent to never delete remote objects
     *
     * @param int $contentid
     */
    public function remove_file($contentid) {
        if (!self::is_file_removable($contentid)) {
            // Don't remove the file - it's still in use.
            return;
        }

        if ($this->is_file_readable_remotely_by_id($contentid)) {
            // We never delete remote objects.
            return;
        }

        if (!$this->is_file_readable_locally_by_id($contentid)) {
            // The file wasn't found in the first place. Just ignore it.
            return;
        }

        $trashpath  = $this->get_trash_fulldir_from_id($contentid);
        $trashfile  = $this->get_trash_fullpath_from_id($contentid);
        $contentfile = $this->get_local_path_from_id($contentid);

        if (!is_dir($trashpath)) {
            mkdir($trashpath, $this->dirpermissions, true);
        }

        if (file_exists($trashfile)) {
            // A copy of this file is already in the trash.
            // Remove the old version.
            unlink($contentfile);
            return;
        }

        // Move the contentfile to the trash, and fix permissions as required.
        rename($contentfile, $trashfile);

        // Fix permissions, only if needed.
        $currentperms = octdec(substr(decoct(fileperms($trashfile)), -4));
        if ((int)$this->filepermissions !== $currentperms) {
            chmod($trashfile, $this->filepermissions);
        }
    }

    /**
     * Scheduled tasks for S3
     */
    public static function get_cron() {

        return array(
            (object)array(
                'callfunction' => 'cron',
                'hour'         => '*',
                'minute'       => '*',
            ),
        );
    }

   /**
    * Push to S3
    */
    public static function cron() {
        //$config = get_objectfs_config();

        $timestamp = date('Y-m-d H:i:s');
        set_config_plugin('module', 'objectfs', 'lastrun', $timestamp);

//        if (isset($config->enabletasks) && $config->enabletasks) {
            $filesystem = new s3_file_system_ArtefactTypeFile();
            $pusher = new pusher($filesystem, $config);
            $candidateids = $pusher->get_candidate_objects();
            $pusher->execute($candidateids);
//        } else {
//            log_debug(get_string('not_enabled', 'module_objectfs'));
//        }
    }

}
