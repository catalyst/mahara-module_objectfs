<?php
/**
 * S3 client.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\client;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/aws/sdk/aws-autoloader.php');
require_once($CFG->docroot . 'module/objectfs/classes/client/object_client.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

define('AWS_API_VERSION', '2006-03-01');

define('AWS_CAN_READ_OBJECT', 0);
define('AWS_CAN_WRITE_OBJECT', 1);
define('AWS_CAN_DELETE_OBJECT', 2);

class s3_client implements object_client {

    protected $client;
    protected $bucket;

    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->set_client($config);
    }

    public function __wakeup() {
        // We dont want to store credentials in the client itself as
        // it will be serialised, so re-retrive them now.
        $config = get_objectfs_config();
        $this->set_client($config);
    }
    public function set_client($config) {
        $this->client = S3Client::factory(array(
            'credentials' => array('key' => $config->key, 'secret' => $config->secret),
            'region' => $config->region,
            'version' => AWS_API_VERSION
        ));
    }

    public function register_stream_wrapper() {
        // Registers 's3://bucket' as a prefix for file actions.
        $this->client->registerStreamWrapper();
    }

    public function get_remote_md5_from_id($contentid) {
        try {
            $key = $this->get_remote_filepath_from_id($contentid);
            $result = $this->client->headObject(array(
                'Bucket' => $this->bucket,
                'Key' => $key));
        } catch (S3Exception $e) {
            return false;
        }

        $md5 = trim($result['ETag'], '"'); // Strip quotation marks.

        return $md5;
    }

    public function verify_remote_object($contentid, $localpath) {
        $localmd5 = md5_file($localpath);
        $remotemd5 = $this->get_remote_md5_from_id($contentid);
        if ($localmd5 === $remotemd5) {
            return true;
        }
        return false;
    }

    /**
     * Returns s3 fullpath to use with php file functions.
     *
     * @param  int $contentid contentid used as key in s3.
     * @return string fullpath to s3 object.
     */
    public function get_remote_fullpath_from_id($contentid) {
        $filepath = $this->get_remote_filepath_from_id($contentid);
        return "s3://$this->bucket/$filepath";
    }

    protected function get_remote_filepath_from_id($contentid) {
        $l = $contentid;
        return "$l/$contentid";
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function test_connection() {
        $connection = new \stdClass();
        $connection->success = true;
        $connection->message = '';
        try {
            $result = $this->client->headBucket(array(
                'Bucket' => $this->bucket));
            $connection->message = get_string('settings:connectionsuccess', 'module.objectfs');
        } catch (S3Exception $e) {
            $connection->success = false;
            $details = $this->get_exception_details($e);
            $connection->message = get_string('settings:connectionfailure', 'module.objectfs') . $details;
        }
        return $connection;
    }

    /**
     * Tests connection to S3 and bucket.
     * There is no check connection in the AWS API.
     * We use list buckets instead and check the bucket is in the list.
     *
     * @return boolean true on success, false on failure.
     */
    public function permissions_check() {

        $permissions = new \stdClass();
        $permissions->success = true;
        $permissions->messages = array();

        try {
            $result = $this->client->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => 'permissions_check_file',
                'Body' => 'test content'));
        } catch (S3Exception $e) {
            $details = $this->get_exception_details($e);
            $permissions->messages[] = get_string('settings:writefailure', 'module.objectfs') . $details;
            $permissions->success = false;
        }

        try {
            $result = $this->client->getObject(array(
                'Bucket' => $this->bucket,
                'Key' => 'permissions_check_file'));
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Write could have failed.
            if ($errorcode !== 'NoSuchKey') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:readfailure', 'module.objectfs') . $details;
                $permissions->success = false;
            }
        }

        try {
            $result = $this->client->deleteObject(array(
                'Bucket' => $this->bucket,
                'Key' => 'permissions_check_file'));
            $permissions->messages[] = get_string('settings:deletesuccess', 'module.objectfs');
            $permissions->success = false;
        } catch (S3Exception $e) {
            $errorcode = $e->getAwsErrorCode();
            // Something else went wrong.
            if ($errorcode !== 'AccessDenied') {
                $details = $this->get_exception_details($e);
                $permissions->messages[] = get_string('settings:deleteerror', 'module.objectfs') . $details;
            }
        }

        if ($permissions->success) {
            $permissions->messages[] = get_string('settings:permissioncheckpassed', 'module.objectfs');
        }

        return $permissions;

    }

    protected function get_exception_details($exception) {
        $message = $exception->getMessage();
        if (get_class($exception) !== 'S3Exception') {
            return "Not a S3 exception : $message";
        }
        $errorcode = $exception->getAwsErrorCode();
        $details = ' ';
        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }
        if ($errorcode) {
            $details .= "ERROR CODE: " . $errorcode . "\n";
        }
        return $details;
    }
}
