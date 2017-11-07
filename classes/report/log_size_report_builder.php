<?php
/**
 * Log size report
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report_builder.php');

defined('INTERNAL') || die();

class log_size_report_builder extends objectfs_report_builder {

    public function build_report() {

        $report = new objectfs_report('log_size');

        $sql = "SELECT log as datakey,
                       sum(size) as objectsum,
                       count(*) as objectcount
                  FROM (SELECT DISTINCT artefact, size, floor(log(2,size)) AS log
                          FROM artefact_file_files
                         WHERE size != 0) d
              GROUP BY log ORDER BY log";

        $stats = get_records_sql_array($sql);

        if (!empty($stats)) {
            $this->compress_small_log_sizes($stats);

            $report->add_rows($stats);
        }

        return $report;
    }

    protected function compress_small_log_sizes(&$stats) {
        $smallstats = new \stdClass();
        $smallstats->datakey = 'small';
        $smallstats->objectsum = 0;
        $smallstats->objectcount = 0;

        foreach ($stats as $key => $stat) {

            // Logsize of <= 19 means that files are smaller than 1 MB.
            if ($stat->datakey <= 19) {
                $smallstats->objectcount += $stat->objectcount;
                $smallstats->objectsum += $stat->objectsum;
                unset($stats[$key]);
            }

        }
        // Add to the beginning of the array.
        array_unshift($stats, $smallstats);
    }
}
