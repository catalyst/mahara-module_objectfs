<?php
/**
 * Object location report builder.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;
require_once($CFG->docroot . '/module/objectfs/classes/report/objectfs_report_builder.php');

class location_report_builder extends objectfs_report_builder {

    function __construct() {
    }

    public function build_report() {

        $locations = array('local'      => OBJECT_LOCATION_LOCAL,
                           'duplicated' => OBJECT_LOCATION_DUPLICATED,
                           'remote'     => OBJECT_LOCATION_REMOTE,
                           'error'      => OBJECT_LOCATION_ERROR);

        $totalcount = 0;
        $totalsum = 0;

        $report = array();

        foreach ($locations as $key => $value) {

            if ($value == OBJECT_LOCATION_LOCAL) {
                $localsql = ' or o.location IS NULL';
            } else {
                $localsql = '';
            }

            $sql = 'SELECT count(sub.artefact) as objectcount, SUM(sub.filesize) as objectsum
              FROM (SELECT af.artefact, MAX(af.size) AS filesize
                      FROM artefact_file_files af
                      LEFT JOIN module_objectfs_objects o on af.artefact = o.contentid
                      GROUP BY af.artefact, af.size, o.location
                      HAVING o.location = ?' . $localsql .' ) AS sub 
              WHERE sub.filesize != 0';

            $result = get_record_sql($sql, array($value));

            $tmp = new \stdClass();
            $tmp->datakey = $key;
            $tmp->reporttype = 0;
            $tmp->objectcount = $result->objectcount;
            $tmp->objectsum = $result->objectsum;

            if (is_null($tmp->objectcount)) {
                $tmp->objectcount =0;
            }

            if (is_null($tmp->objectsum)) {
                $tmp->objectsum =0;
            }

            $report[] = $tmp;

            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;

        }

        $tmp = new \stdClass();
        $tmp->datakey = 'total';
        $tmp->reporttype = 0;
        $tmp->objectcount = $totalcount;
        $tmp->objectsum = $totalsum;

        if (is_null($tmp->objectcount)) {
            $tmp->objectcount = 0;
        }

        if (is_null($tmp->objectsum)) {
            $tmp->objectsum = 0;
        }

        $report['total'] = $tmp;

        return $report;
    }

}
