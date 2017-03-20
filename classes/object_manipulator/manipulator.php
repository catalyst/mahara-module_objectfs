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

    /**
     * Manipulator constructor
     *
     * @param PluginModuleObjectfs $filesystem object file system
     * @param int $maxruntime What time the file manipulator should finish execution by
     */
    public function __construct($filesystem, $config) {
//        $this->finishtime = time() + $config->maxtaskruntime;
        $this->filesystem = $filesystem;
    }

    /**
     * get candidate content ids for execution.
     *
     * @return array $candidateids candidate content ids
     */
    abstract public function get_candidate_objects();

    /**
     * execute file manipulation.
     *
     * @param  array $candidateids candidate content ids
     */
    abstract public function execute($candidateids);
}
