<?php

//require_once(dirname(dirname(dirname(__FILE__))).'/init.php');
global $CFG;
require_once($CFG->docroot . '/artefact/file/remote_file_system.php');
require_once($CFG->docroot . '/module/objectfs/classes/client/s3_client.php');

class objectfs_file_system extends remote_file_system {

    function __construct() {
        $config = get_objectfs_config();
        $this->client = new \module_objectfs\client\s3_client($config);
        $this->client->register_stream_wrapper();
    }

    public function get_path($fileartefact, $data = array()) {
        $localpath = $fileartefact->get_local_path($data);

        if (is_readable($localpath)) {
            return $localpath;
        } else {
            return $this->client->get_remote_fullpath_from_id($fileartefact->get('id'));
        }
    }

    public function is_file_readable_locally($fileartefact, $data = array()) {
        $localpath = $fileartefact->get_local_path($data);

        if (is_readable($localpath)) {
            return true;
        } else {
            return false;
        }
    }

    public function is_file_readable_remotely($fileartefact, $data = array()) {
        $remotepath = $this->client->get_remote_fullpath_from_id($fileartefact->get('id'));

        if (is_readable($remotepath)) {
            return true;
        } else {
            return false;
        }
    }

    public function ensure_local($fileartefact) {

        $fileid = $fileartefact->get('fileid');
        $success = $this->copy_object_from_remote_to_local($fileartefact);

        if ($success) {
            $location = OBJECT_LOCATION_DUPLICATED;
        } else {
            $location = $this->get_actual_object_location($fileartefact);
        }

        update_object_record($fileid, $location);
    }


    protected function get_object_path_from_storedfile($file) {
/*        if ($this->preferremote) {
            $location = $this->get_actual_object_location_by_hash($file->get_contenthash());
            if ($location == OBJECT_LOCATION_DUPLICATED) {
                return $this->get_remote_path_from_storedfile($file);
            }
        }*/

        if (is_readable($this->get_local_path_from_id($file))) {
            $path = $this->get_local_path_from_id($file);
        } else {
            // We assume it is remote, not checking if it's readable.
            $path = $this->get_remote_path_from_id($file->get('fileid'));
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
     * @param bool $fetchifnotfound Whether to attempt to fetch from the remote path if not found.
     * @return string The full path to the content file
     */
    protected function get_local_path_from_id($content) {
        $path = $content->get_local_path();
        return $path;
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
        return $this->client->get_remote_fullpath_from_id($contentid);
    }

    public function get_actual_object_location($content) {

        $localpath = $this->get_local_path_from_id($content);
        $remotepath = $this->get_remote_path_from_id($content->get('fileid'));

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

    public function copy_object_from_remote_to_local($content) {
        $location = $this->get_actual_object_location($content);

        // Already duplicated.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            return true;
        }

        if ($location === OBJECT_LOCATION_REMOTE) {

            $localpath = $this->get_local_path_from_id($content);
            $remotepath = $this->get_remote_path_from_id($content->get('fileid'));

            $objectlock = $this->acquire_object_lock($content->get('fileid'));

            // Lock is still held by something.
            if (!$objectlock) {
                return false;
            }

            // While waiting for lock, file was moved.
            if (($localpath !== $remotepath) && is_readable($localpath)) {
                $this->release_object_lock($content->get('fileid'));
                return true;
            }

            $result = copy($remotepath, $localpath);

            $this->release_object_lock($content->get('fileid'));

            return $result;
        }
        return false;
    }

    public function copy_object_from_local_to_remote($content) {
        $location = $this->get_actual_object_location($content);

        // Already duplicated.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            return true;
        }

        if ($location === OBJECT_LOCATION_LOCAL) {

            $localpath = $this->get_local_path_from_id($content);
            $remotepath = $this->get_remote_path_from_id($content->get('fileid'));

            $objectlock = $this->acquire_object_lock($content->get('fileid'));

            // Lock is still held by something.
            if (!$objectlock) {
                return false;
            }

            // While waiting for lock, file was moved.
            if (is_readable($remotepath)) {
                $this->release_object_lock($content->get('fileid'));
                return true;
            }

            $result = copy($localpath, $remotepath);

            $this->release_object_lock($content->get('fileid'));

            return $result;
        }
        return false;
    }

    public function delete_object_from_local($content) {
        $location = $this->get_actual_object_location($content);

        // Already deleted.
        if ($location === OBJECT_LOCATION_REMOTE) {
            return true;
        }

        // We want to be very sure it is remote if we're deleting objects.
        // There is no going back.
        if ($location === OBJECT_LOCATION_DUPLICATED) {
            $localpath = $this->get_local_path_from_id($content);
            $objectvalid = $this->client->verify_remote_object($content->get('fileid'), $localpath);
            if ($objectvalid) {
                return unlink($localpath);
            }
        }

        return false;
    }

}
