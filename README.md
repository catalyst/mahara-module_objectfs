<a href="https://travis-ci.org/catalyst/moodle-module_objectfs">
<img src="https://travis-ci.org/catalyst/moodle-module_objectfs.svg?branch=master">
</a>

# mahara-module_objectfs

A remote object storage file system for Mahara. Intended to provide a plug-in that can be installed and configured to work with any supported remote object storage solution. This plug-in requires [mahara-module_aws](https://github.com/catalyst/mahara-module_aws) to function.

* [Use cases](#use-cases)
  * [Offloading large and old files to save money](#offloading-large-and-old-files-to-save-money)
  * [Sharing files across maharas to save disk](#sharing-files-across-maharas-to-save-disk)
  * [Sharing files across environments to save time](#sharing-files-across-environments-to-save-time)
* [Installation](#installation)
* [Currently supported object stores](#currently-supported-object-stores)
  * [Roadmap](#roadmap)
  * [Amazon S3](#amazon-s3)
* [Mahara configuration](#mahara-configuration)
  * [General Settings](#general-settings)
  * [File Transfer settings](#file-transfer-settings)
  * [Amazon S3 settings](#amazon-s3-settings)
* [Backporting](#backporting)
* [Crafted by Catalyst IT](#crafted-by-catalyst-it)
* [Contributing and support](#contributing-and-support)

## Use cases
There are a number of different ways you can use this plug in. See [Recommended use case settings](#recommended-use-case-settings) for recommended settings for each one.

### Offloading large and old files to save money

Disk can be expensive, so a simple use case is we simply want to move some of the largest and oldest files off local disk to somewhere cheaper. But we still want the convenience and performance of having the majority of files local, especially if you are hosting on-prem where the latency or bandwidth to the remote filesystem may not be great.

### Sharing files across maharas to save disk

Many of our clients have multiple mahara instances, and there is much duplicated content across instances. By pointing multiple maharas at the same remote filesystem, and not allowing deletes, then large amounts of content can be de-duplicated.

### Sharing files across environments to save time

Some of our clients maharas are truly massive. We also have multiple environments for various types of testing, and often have ad hoc environments created on demand. Not only do we not want to have to store duplicated files, but we also want refreshing data to new environments to be as fast as possible.

Using this plugin we can configure production to have full read write to the remote filesystem and store the vast bulk of content remotely. In this setup the latency and bandwidth isn't an issue as they are colocated. The local filedir on disk would only consist of small or fast churning files. A refresh of the production data back to a staging environment can be much quicker now as we skip the sitedir clone completely and stage is simple configured with readonly access to the production filesystem. Any files it creates would only be written to it's local filesystem which can then be discarded when next refreshed.

## Installation
1. If not on Mahara 17.04, backport the file system API. See [Backporting](#backporting)
2. Setup your remote object storage. See [Remote object storage setup](#remote-object-storage-setup)
3. Clone this repository into module/objectfs
4. Clone [mahara-module_aws](https://github.com/catalyst/mahara-module_aws) into module/aws
4. Install the plugins through the mahara GUI.
5. Configure the plugin. See [Mahara configuration](#mahara-configuration)
```
$cfg->alternative_file_system = 1;
```

## Currently supported object stores

### Roadmap

There is support for more object stores planed, in particular enabling Openstack deployments.

### Amazon S3

*Amazon S3 bucket setup*

- Create an Amazon S3 bucket.
- The AWS Users access policy should mirror the policy listed below.
- Replace 'bucketname' with the name of your S3 bucket.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["arn:aws:s3:::bucketname"]
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": ["arn:aws:s3:::bucketname/*"]
    }
  ]
}
```

## Mahara configuration
Go to Administration -> Extensions -> objectfs. Descriptions for the various settings are as follows:

### General Settings
- **Enable file transfer tasks**: Enable or disable the object file system tasks which move files between the filedir and remote object storage.
- **Maximum task runtime**: Background tasks handle the transfer of objects to and from remote object storage. This setting controls the maximum runtime for all object transfer related tasks.
- **Prefer remote objects**: If a file is stored both locally and in remote object storage, read from remote. This is setting is mainly for testing purposes and introduces overhead to check the location.

### File Transfer settings
These settings control the movement of files to and from object storage.

- **Minimum size threshold (KB)**: Minimum size threshold for transferring objects to remote object storage. If objects are over this size they will be transfered.
- **Minimum age**: Minimum age that a object must exist on the local filedir before it will be considered for transfer.
- **Delete local objects**: Delete local objects once they are in remote object storage after the consistency delay.
- **Consistency delay**: How long an object must have existed after being transfered to remote object storage before they are a candidate for deletion locally.

### Amazon S3 settings
S3 specific settings
- **Key**: AWS credential key
- **Secret**: AWS credential secret
- **Bucket**: S3 bucket name to store files in
- **AWS region**: AWS API endpoint region to use.


## Backporting

If you are on an older mahara then you can backport the necessary API's in order to support this plugin. Use with caution!

Crafted by Catalyst IT
----------------------

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

![Catalyst IT](/pix/catalyst-logo.png?raw=true)


Contributing and support
------------------------

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/mahara-module_objectfs/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us
