<?php
/**
 * File manipulator abstract class.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\object_manipulator;

use module_objectfs\log\aggregate_logger;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/lib.php');

abstract class manipulator {

    /**
     * PluginModuleObjectfs
     *
     * @var PluginModuleObjectfs
     */
    protected $filesystem;

    /**
     * What time the file manipulator should finish execution by.
     *
     * @var int
     */
    protected $finishtime;

    protected $logger;

    /**
     * Manipulator constructor
     *
     * @param objectfs_file_system $filesystem object file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($filesystem, $config) {
        $this->finishtime = time() + $config->maxtaskruntime;
        $this->filesystem = $filesystem;
    }

    /**
     * get candidate content ids for execution.
     *
     * @return array $candidateids candidate content ids
     */
    abstract public function get_candidate_objects();

    /**
     * Pushes files from local file system to remote.
     *
     * @param array $candidateids content ids to push
     */
    public function execute($objectrecords) {
        if (!$this->manipulator_can_execute()) {
            log_debug('Objectfs manipulator exiting early');
            return;
        }
        if (count($objectrecords) == 0) {
            log_debug('No candidate objects found.');
            return;
        }
        $this->logger->start_timing();
        foreach ($objectrecords as $objectrecord) {
            if (time() >= $this->finishtime) {
                break;
            }
//            $objectlock = $this->filesystem->acquire_object_lock($objectrecord->contentid); // FIX this
            // Object is currently being manipulated elsewhere.
//            if (!$objectlock) {
//                continue;
//            }
            $this->filesystem->set('fileid', $objectrecord->artefact);
            $newlocation = $this->manipulate_object($objectrecord);
            update_object_record($objectrecord->artefact, $newlocation);
//            $objectlock->release();
        }
        $this->logger->end_timing();
        $this->logger->output_move_statistics();
    }
    protected function manipulator_can_execute() {
        return true;
    }
    public static function get_all_manipulator_classnames() {
        $manipulators = array('deleter',
                              'puller',
                              'pusher',
                              'recoverer');
        foreach ($manipulators as $key => $manipulator) {
            $manipulators[$key] = '\\module_objectfs\\object_manipulator\\' . $manipulator;
        }
        return $manipulators;
    }
    public static function setup_and_run_object_manipulator($manipulatorclassname) {
        global $CFG;
        require_once($CFG->docroot . 'module/objectfs/s3_file_system.php');
        require_once($CFG->docroot . 'module/objectfs/classes/log/aggregate_logger.php');

        $config = get_objectfs_config();
        $shouldtaskrun = module_objectfs_should_tasks_run();
        if ($shouldtaskrun) {
            $logger = new aggregate_logger();
            $filesystem = new \ArtefactTypeFile_s3_file_system();
            $manipulatorclassname = '\\module_objectfs\\object_manipulator\\' . $manipulatorclassname;
            $manipulator = new $manipulatorclassname($filesystem, $config, $logger);
            $candidateids = $manipulator->get_candidate_objects();
            $manipulator->execute($candidateids);
        } else {
            log_debug(get_string('not_enabled', 'module.objectfs'));
        }
    }
}
