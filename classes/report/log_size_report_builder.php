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

class log_size_report_builder extends location_report_builder {

    protected static $reporttype = 'log_size';

    public function build_report() {

        $stats = array();
        $small = array();
        foreach ($this->files_by_location() as $location => $files) {
            if (!$files) continue;

            foreach ($files as $f) {
                if ($f->log <= 19) {
                    if (isset($small[$location])) {
                        $l = $small[$location];
                    } else {
                        $small[$location] = $l = new \stdClass();
                        $l->objectcount = 0;
                        $l->objectsum = 0;
                    }
                    $l->objectcount += $f->objectcount;
                    $l->objectsum += $f->objectsum;
                    continue;
                }

                $f->datakey = implode(":", $f->log, $location);
                $stats[] = $f;
            }
        }
        foreach ($small as $location => $f) {
            $f->datakey = implode(":", ['small', $location]);
            // Add to the beginning of the array.
            array_unshift($stats, $f);
        }

        return $stats;
    }

    protected function format_report($rows) {

        $file_location_names = array_values(self::file_locations());

        $stats = array();
        foreach ($rows as $row) {
            list($log, $location) = explode(":", $row->datakey);
            $datakey = $this->get_size_range_from_logsize($log); // Turn logsize into a byte range.
            $ln = self::file_location_code_to_name($location);
            if (isset($stats[$datakey])) {
                $l = $stats[$datakey];
            } else {
                $stats[$datakey] = $l = new \stdClass();
                $l->datakey = $datakey;
                $l->objectsum = 0;
                $l->objectcount = 0;
                foreach($file_location_names as $fln) {
                    $l->$fln = 0;
                }
            }
            $l->$ln += $row->objectsum;
            $l->objectsum += $row->objectsum;
            $l->objectcount += $row->objectcount;
        }

        return array_values($stats);
    }

    private function get_size_range_from_logsize($logsize) {

	// Small logsizes have been compressed.
	if ($logsize == 'small') {
		return '< 1MB';
	}

	$floor = pow(2, $logsize);
	$roof = ($floor * 2);
	$floor = display_size($floor);
	$roof = display_size($roof);
	$sizerange = "$floor - $roof";

	return $sizerange;
    }
}
