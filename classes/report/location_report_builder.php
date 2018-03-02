<?php
/**
 * Object location report builder.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report_builder.php');
require_once(get_config('docroot') . 'module/objectfs/objectfslib.php');

defined('INTERNAL') || die();

class location_report_builder extends objectfs_report_builder {

    protected static $reporttype = 'location';

    protected static function file_locations() {
        return array(
            OBJECT_LOCATION_LOCAL      => 'local',
            OBJECT_LOCATION_DUPLICATED => 'duplicated',
            OBJECT_LOCATION_EXTERNAL   => 'external',
            OBJECT_LOCATION_ERROR      => 'error',
        );
    }

    protected static function file_location_code_to_name($code) {
        return self::file_locations()[$code];
    }

    protected function files_by_location() {

        $l = array();
        foreach (array_keys(self::file_locations()) as $location) {

            if ($location == OBJECT_LOCATION_LOCAL) {
                $localsql = ' or o.location IS NULL';
            } else {
                $localsql = '';
            }

            $sql = 'SELECT count(sub.artefact) as objectcount, SUM(sub.filesize) as objectsum, sub.log
                      FROM (SELECT af.artefact, MAX(af.size) AS filesize, floor(log(2,size)) AS log
                              FROM artefact_file_files af
                         LEFT JOIN module_objectfs_objects o on af.artefact = o.contentid
                             WHERE af.size != 0 AND (o.location = ?' . $localsql .')
                          GROUP BY af.artefact, af.size, o.location, floor(log(2,size))) AS sub
                      GROUP BY sub.log';

            $l[$location] = get_records_sql_array($sql, array($location));
        }
        return $l;
    }

    public function build_report() {

        $totalcount = 0;
        $totalsum = 0;

        $report = array();
        foreach ($this->files_by_location() as $location => $files) {

            $result = new \StdClass();
            $result->datakey = $location;
            $result->objectcount = 0;
            $result->objectsum = 0;
            $report[] = $result;

            if (empty($files)) continue;

            foreach ($files as $f) {
                $result->objectcount += $f->objectcount;
                $result->objectsum += $f->objectsum;
            }

            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;
        }

        $total = new \stdClass();
        $total->datakey = 'total';
        $total->objectcount = $totalcount;
        $total->objectsum = $totalsum;
        $report[] = $total;

        return $report;
    }

    protected function format_report($rows) {

        foreach ($rows as $row) {

            $row->datakey = $this->get_file_location_string($row->datakey); // Turn int location into string.
        }

        return $this->augment_barchart($rows);
    }

    private function get_file_location_string($filelocation) {

        if ($filelocation == 'total') {
            return get_string('object_status:location:total', 'module.objectfs');
        }

        switch ($filelocation){
            case OBJECT_LOCATION_ERROR:
                return get_string('object_status:location:error', 'module.objectfs');
            case OBJECT_LOCATION_LOCAL:
                return get_string('object_status:location:local', 'module.objectfs');
            case OBJECT_LOCATION_DUPLICATED:
                return get_string('object_status:location:duplicated', 'module.objectfs');
            case OBJECT_LOCATION_EXTERNAL:
                return get_string('object_status:location:external', 'module.objectfs');
            default:
                return get_string('object_status:location:unknown', 'module.objectfs');
        }
    }
}
