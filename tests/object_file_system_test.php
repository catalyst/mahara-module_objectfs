<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_objectfs\tests;

defined('MOODLE_INTERNAL') || die();

use tool_objectfs\object_file_system;

require_once(__DIR__ . '/classes/test_client.php');
require_once(__DIR__ . '/tool_objectfs_testcase.php');

class object_file_system_testcase extends tool_objectfs_testcase {

    public function test_get_object_path_from_storedfile_returns_local_path_if_local() {
        $file = $this->create_local_file();
        $expectedpath = $this->get_local_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_object_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_object_path_from_storedfile_returns_remote_path_if_not_local() {
        $file = $this->create_remote_file();
        $expectedpath = $this->get_remote_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_object_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_object_path_from_storedfile_returns_remote_path_if_duplicated_and_preferremote() {
        set_config('preferremote', true, 'tool_objectfs');
        $this->reset_file_system(); // Needed to load new config.
        $file = $this->create_duplicated_file();
        $expectedpath = $this->get_remote_path_from_storedfile($file);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_object_path_from_storedfile');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$file]);

        $this->assertEquals($expectedpath, $actualpath);
    }

    public function test_get_local_path_from_hash_will_fetch_remote_if_fetchifnotfound() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $expectedpath = $this->get_local_path_from_hash($filehash);

        $reflection = new \ReflectionMethod(object_file_system::class, 'get_local_path_from_hash');
        $reflection->setAccessible(true);
        $actualpath = $reflection->invokeArgs($this->filesystem, [$filehash, true]);

        $this->assertEquals($expectedpath, $actualpath);
        $this->assertTrue(is_readable($actualpath));
    }

    public function test_copy_object_from_remote_to_local() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $result = $this->filesystem->copy_object_from_remote_to_local_by_hash($filehash);

        $this->assertTrue($result);
        $this->assertTrue(is_readable($localpath));
    }

    public function test_copy_object_from_remote_to_local_by_hash_fails_if_local() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();

        $result = $this->filesystem->copy_object_from_remote_to_local_by_hash($filehash);

        $this->assertFalse($result);
    }

    public function test_copy_object_from_remote_to_local_by_hash_succeeds_if_already_duplicated() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();

        $result = $this->filesystem->copy_object_from_remote_to_local_by_hash($filehash);

        $this->assertTrue($result);
    }

    public function test_copy_object_from_remote_to_local_by_hash_fails_if_not_local_and_not_remote() {
        $fakehash = 'this is a fake hash';

        $result = $this->filesystem->copy_object_from_remote_to_local_by_hash($fakehash);

        $this->assertFalse($result);
    }

    public function test_copy_object_from_local_to_remote_by_hash() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $remotepath = $this->get_remote_path_from_storedfile($file);

        $result = $this->filesystem->copy_object_from_local_to_remote_by_hash($filehash);

        $this->assertTrue($result);
        $this->assertTrue(is_readable($remotepath));
    }

    public function test_copy_object_from_local_to_remote_by_hash_fails_if_remote() {
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();

        $result = $this->filesystem->copy_object_from_local_to_remote_by_hash($filehash);

        $this->assertFalse($result);

    }

    public function test_copy_object_from_local_to_remote_by_hash_succeeds_if_already_duplicated() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();

        $result = $this->filesystem->copy_object_from_local_to_remote_by_hash($filehash);

        $this->assertTrue($result);
    }

    public function test_copy_object_from_local_to_remote_by_hash_fails_if_not_local_and_not_remote() {
        $fakehash = 'this is a fake hash';

        $result = $this->filesystem->copy_object_from_local_to_remote_by_hash($fakehash);

        $this->assertFalse($result);
    }

    public function test_delete_object_from_local_by_hash() {
        $file = $this->create_duplicated_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $result = $this->filesystem->delete_object_from_local_by_hash($filehash);

        $this->assertTrue($result);
        $this->assertFalse(is_readable($localpath));
    }

    public function test_delete_object_from_local_by_hash_fails_if_not_remote() {
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();
        $localpath = $this->get_local_path_from_storedfile($file);

        $result = $this->filesystem->delete_object_from_local_by_hash($filehash);

        $this->assertFalse($result);
        $this->assertTrue(is_readable($localpath));

    }

    public function test_delete_object_from_local_by_hash_fails_if_not_local() {
        $fakehash = 'this is a fake hash';

        $result = $this->filesystem->delete_object_from_local_by_hash($fakehash);

        $this->assertFalse($result);
    }

    public function test_delete_object_from_local_by_hash_fails_if_verify_remote_object_fails() {
        $file = $this->create_duplicated_file();
        $remotepath = $this->get_remote_path_from_hash($file->get_contenthash());
        $localpath = $this->get_local_path_from_storedfile($file);

        unlink($remotepath);
        $differentfilepath = __DIR__ . '/fixtures/test.txt';
        copy($differentfilepath, $remotepath);

        $result = $this->filesystem->delete_object_from_local_by_hash($file->get_contenthash());

        $this->assertFalse($result);
        $this->assertTrue(is_readable($localpath));
    }

    public function test_readfile_if_object_is_local() {
        $expectedcontent = 'expected content';
        $file = $this->create_local_file($expectedcontent);

        $this->expectOutputString($expectedcontent);

        $this->filesystem->readfile($file);
    }

    public function test_readfile_if_object_is_remote() {
        $expectedcontent = 'expected content';
        $file = $this->create_remote_file($expectedcontent);

        $this->expectOutputString($expectedcontent);

        $this->filesystem->readfile($file);
    }

    public function test_get_content_if_object_is_local() {
        $expectedcontent = 'expected content';
        $file = $this->create_local_file($expectedcontent);

        $actualcontent = $this->filesystem->get_content($file);

        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_get_content_if_object_is_remote() {
        $expectedcontent = 'expected content';
        $file = $this->create_remote_file($expectedcontent);

        $actualcontent = $this->filesystem->get_content($file);

        $this->assertEquals($expectedcontent, $actualcontent);
    }

    public function test_get_content_file_handle_if_object_is_local() {
        $file = $this->create_local_file();

        $filehandle = $this->filesystem->get_content_file_handle($file);

        $this->assertTrue(is_resource($filehandle));
    }

    public function test_get_content_file_handle_if_object_is_remote() {
        $file = $this->create_remote_file();

        $filehandle = $this->filesystem->get_content_file_handle($file);

        $this->assertTrue(is_resource($filehandle));
    }

    public function test_get_content_file_handle_will_pull_remote_object_if_gzopen() {
        $file = $this->create_remote_file();
        $localpath = $this->get_local_path_from_storedfile($file);

        $filehandle = $this->filesystem->get_content_file_handle($file, \stored_file::FILE_HANDLE_GZOPEN);

        $this->assertTrue(is_resource($filehandle));
        $this->assertTrue(is_readable($localpath));
    }

    public function test_remove_file_will_remove_local_file() {
        global $DB;
        $file = $this->create_local_file();
        $filehash = $file->get_contenthash();

        // Delete file record so remove file will remove.
        $DB->delete_records('files', array('contenthash' => $filehash));
        $this->filesystem->remove_file($filehash);

        $islocalreadable = $this->filesystem->is_file_readable_locally_by_hash($filehash);
        $this->assertFalse($islocalreadable);
    }

    public function test_remove_file_will_not_remove_remote_file() {
        global $DB;
        $file = $this->create_remote_file();
        $filehash = $file->get_contenthash();

        // Delete file record so remove file will remove.
        $DB->delete_records('files', array('contenthash' => $filehash));
        $this->filesystem->remove_file($filehash);

        $isremotereadable = $this->filesystem->is_file_readable_remotely_by_hash($filehash);
        $this->assertTrue($isremotereadable);
    }
}

