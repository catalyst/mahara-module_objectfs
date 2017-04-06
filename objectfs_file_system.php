<?php

//require_once(dirname(dirname(dirname(__FILE__))).'/init.php');
require_once($CFG->docroot . '/artefact/file/remote_file_system.php');
require_once($CFG->docroot . '/module/objectfs/classes/client/s3_client.php');

class objectfs_file_system extends remote_file_system {

    function __construct() {
        $config = get_objectfs_config();
        $this->client = new \module_objectfs\client\s3_client($config);
        $this->client->register_stream_wrapper();
    }

    public function get_path($fileartifact, $data = array(), $fetchifnotfound = false) {
        $localpath = $fileartifact->get_local_path($data);

        if (is_readable($localpath)) {
            return $localpath;
        }

        if ($fetchifnotfound) {
            $this->ensure_local($fileartifact);
            return $localpath;
        } else {
            return $this->client->get_remote_fullpath_from_id($fileartifact->get('id'));
        }
    }

    public function ensure_local($fileartifact, $data = array()) {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');

        $temp = new ArtefactTypeFile_s3_file_system();
        $success = $temp->copy_object_from_remote_to_local_by_id($fileartifact->get('id'));

        if ($success) {
            $location = OBJECT_LOCATION_DUPLICATED;
        } else {
            $location = $temp->get_actual_object_location_by_id($fileartifact->get('id'));
        }

        update_object_record($fileartifact->get('id'), $location);
    }

}


