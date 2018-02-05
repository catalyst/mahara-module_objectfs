<?php
/**
 * S3 client.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\client;

defined('INTERNAL') || die();

$autoloader = get_config('docroot') . 'module/aws/sdk/aws-autoloader.php';

if (!file_exists($autoloader)) {
    // Stub class with bare implementation for when the SDK prerequisite does not exist.
    class s3_client {
        public function get_availability() {
            return false;
        }
        public function register_stream_wrapper() {
            return false;
        }
    }
    return;
}
require_once($autoloader);

require_once(get_config('docroot') . 'module/objectfs/classes/client/object_client.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

define('AWS_API_VERSION', '2006-03-01');

define('AWS_CAN_READ_OBJECT', 0);
define('AWS_CAN_WRITE_OBJECT', 1);
define('AWS_CAN_DELETE_OBJECT', 2);

class s3_client implements object_client
{

    protected $client;
    protected $bucket;
    protected $defaultconfig;

    public function __construct($config) {
        $this->bucket = $config->bucket;
        $this->defaultconfig = $config;
        $this->setMissingDefaultConfigPropertyToEmptyString('s3_key');
        $this->setMissingDefaultConfigPropertyToEmptyString('s3_secret');
        $this->setMissingDefaultConfigPropertyToEmptyString('s3_bucket');
        $this->setMissingDefaultConfigPropertyToEmptyString('s3_region');

        $this->set_client($config);
    }

    public function __sleep() {
        return array('bucket');
    }

    public function __wakeup() {
        // We dont want to store credentials in the client itself as
        // it will be serialised, so re-retrive them now.
        $config = get_objectfs_config();
        $this->set_client($config);
        $this->client->registerStreamWrapper();
    }

    /**
     * Returns true if the AWS S3 Storage SDK exists and has been loaded.
     *
     * @return bool
     */
    public function get_availability() {
        return true;
    }

    public function set_client($config) {
        $this->client = S3Client::factory(array(
            'credentials' => array('key' => $config->key, 'secret' => $config->secret),
            'region' => $config->region,
            'version' => AWS_API_VERSION
        ));
    }

    /**
     * @return mixed
     */
    public function define_settings_form() {
        $connectiontest = $this->test_connection();
        $permissiontest = $this->test_permissions();

        $regionoptions = array(
            'us-east-1' => 'us-east-1',
            'us-east-2' => 'us-east-2',
            'us-west-1' => 'us-west-1',
            'us-west-2' => 'us-west-2',
            'ap-northeast-2' => 'ap-northeast-2',
            'ap-southeast-1' => 'ap-southeast-1',
            'ap-southeast-2' => 'ap-southeast-2',
            'ap-northeast-1' => 'ap-northeast-1',
            'eu-central-1' => 'eu-central-1',
            'eu-west-1' => 'eu-west-1'
        );

        $config = array(
            'type' => 'fieldset',
            'legend' => get_string('settings:awsheader', 'module.objectfs'),
            'collapsible' => true,
            'collapsed' => true,
            'elements' => array(
                'connectiontest' => array(
                    'title' => get_string('settings:connection', 'module.objectfs'),
                    'type' => 'html',
                    'value' => $connectiontest->message,
                ),
                'permissionstest' => array(
                    'title' => get_string('settings:permissions', 'module.objectfs'),
                    'type' => 'html',
                    'value' => $permissiontest->messages[0],
                ),
                's3_key' => array(
                    'title' => get_string('settings:key', 'module.objectfs'),
                    'description' => get_string('settings:key_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $this->defaultconfig->s3_key,
                ),
                's3_secret' => array(
                    'title' => get_string('settings:secret', 'module.objectfs'),
                    'description' => get_string('settings:secret_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $this->defaultconfig->s3_secret,
                ),
                's3_bucket' => array(
                    'title' => get_string('settings:bucket', 'module.objectfs'),
                    'description' => get_string('settings:bucket_help', 'module.objectfs'),
                    'type' => 'text',
                    'defaultvalue' => $this->defaultconfig->s3_bucket,
                ),
                's3_region' => array(
                    'title' => get_string('settings:region', 'module.objectfs'),
                    'description' => get_string('settings:region_help', 'module.objectfs'),
                    'type' => 'select',
                    'options' => $regionoptions,
                    'defaultvalue' => (!empty($this->defaultconfig->s3_region)) ? $this->defaultconfig->s3_region : 'us-east-1',
                ),
            ),
        );
        return $config;
    }

    protected function setMissingDefaultConfigPropertyToEmptyString($propertyname) {
        if (empty($this->defaultconfig->$propertyname)) {
            $this->defaultconfig->$propertyname = "";
        }
    }

    public function register_stream_wrapper() {
        // Registers 's3://bucket' as a prefix for file actions.
        $this->client->registerStreamWrapper();
    }

    private function get_md5_from_hash($contenthash) {
        try {
            $key = $this->get_filepath_from_hash($contenthash);
            $result = $this->client->headObject(array(
                            'Bucket' => $this->bucket,
                            'Key' => $key));
        } catch (S3Exception $e) {
            return false;
        }

        $md5 = trim($result['ETag'], '"'); // Strip quotation marks.

        return $md5;
    }

    public function verify_object($contenthash, $localpath) {
        $localmd5 = md5_file($localpath);
        $externalmd5 = $this->get_md5_from_hash($contenthash);
        if ($externalmd5) {
            return true;
        }
        return false;
    }

    /**
     * Returns s3 fullpath to use with php file functions.
     *
     * @param  string $contenthash contenthash used as key in s3.
     * @return string fullpath to s3 object.
     */
    public function get_fullpath_from_hash($contenthash) {
        $filepath = $this->get_filepath_from_hash($contenthash);
        return "s3://$this->bucket/$filepath";
    }

    /**
     * S3 file streams require a seekable context to be supplied
     * if they are to be seekable.
     *
     * @return void
     */
    public function get_seekable_stream_context() {
        $context = stream_context_create(array(
            's3' => array(
                'seekable' => true
            )
        ));
        return $context;
    }

    protected function get_filepath_from_hash($contenthash) {
        $l1 = $contenthash[0] . $contenthash[1];
        $l2 = $contenthash[2] . $contenthash[3];
        return "$l1/$l2/$contenthash";
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
    public function test_permissions() {
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
