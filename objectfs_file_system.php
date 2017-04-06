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

    public function get_path($fileartifact, $data = array()) {
        $localpath = $fileartifact->get_local_path($data);

        error_log($this->client->get_remote_fullpath_from_id($fileartifact->get('id')));
        if (is_readable($localpath)) {
            return $localpath;
        }

        return $this->client->get_remote_fullpath_from_id();
    }
}


