<?php
/**
 * Mahara remote file system class.
 *
 * @package     mahara
 * @subpackage  module.objectfs
 * @author      Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs;

defined('INTERNAL') || die();

require_once(get_config('docroot') . 'module/objectfs/classes/object_file_system.php');
require_once(get_config('docroot') . 'artefact/file/externalfilesystem.php');

abstract class mahara_external_filesystem extends object_file_system implements \external_file_system {

    /**
     * Return a file path
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return file path
     */
    public function get_path($fileartefact) {

        return $this->get_remote_path($fileartefact);
    }

    /**
     * Check to see whether or not a file is readable we assume this is external
     *
     * @param stdClass $fileartefact This is the file object
     * 
     * @return bool True if file is readable, false otherwise
     */
    public function is_file_readable($fileartefact) {
        
        $contenthash = $fileartefact->get('contenthash');

        if ($contenthash == hash('sha256', '')) {
            // Files with empty size are either directories or empty.
            // We handle these virtually.
            return true;
        }

        $path = $this->get_external_path_from_hash($contenthash, false);

        // Note - it is not possible to perform content recovery safely from hash alone.
        return is_readable($path);
    }

    /**
     * Ensure that a file is local
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return void
     */
    public function ensure_local($fileartefact) {

        $success = $this->copy_object_from_external_to_local($fileartefact);

        if ($success) {

            $location = OBJECT_LOCATION_DUPLICATED;
        } else {

            $location = $this->get_file_location_status($fileartefact);
        }

        update_object_record($fileartefact, $location);

    }

    /**
     * Return location status of a file
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return int status of file
     */
    public function get_file_location_status($fileartefact) {

        return $this->get_object_location($fileartefact);
    }

    /**
     * Copy a file from an external location to a local location
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return File location
     */
    public function copy_file_from_external_to_local($fileartefact) {

        return $this->copy_object_from_external_to_local($fileartefact, $fileartefact->get('size'));
    }

    /**
     * Copy a file from a local location to an external location
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return File location
     */
    public function copy_file_from_local_to_external($fileartefact) {

        return $this->copy_object_from_local_to_external($fileartefact, $fileartefact->get('size'));
    }

    /**
     * Delete the file from local after it has been transferred to external
     *
     * @param stdClass $fileartefact This is the file object
     *
     * @return
     */
    public function delete_file($fileartefact) {

        return $this->delete_object_from_local($fileartefact, $fileartefact->get('size'));
    }

    /**
     * Will return a externally seekable file handle for supplied path and mode
     *
     * @param string $path object path
     * @param string $mode fopen mode
     * @return file pointer resource on success, or FALSE on error
     */
    public function get_file_handle($path, $mode) {
        return $this->get_object_file_handle($path, $mode);
    }
}
