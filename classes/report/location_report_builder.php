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

defined('INTERNAL') || die();

class location_report_builder extends objectfs_report_builder {

    public function build_report() {

        $report = new objectfs_report('location');

        $locations = array(OBJECT_LOCATION_LOCAL,
                           OBJECT_LOCATION_DUPLICATED,
                           OBJECT_LOCATION_EXTERNAL,
                           OBJECT_LOCATION_ERROR);

        $totalcount = 0;
        $totalsum = 0;

        foreach ($locations as $location) {

            if ($location == OBJECT_LOCATION_LOCAL) {
                $localsql = ' or o.location IS NULL';
            } else {
                $localsql = '';
            }

            $sql = 'SELECT COALESCE(count(sub.contenthash) ,0) AS objectcount,
                           COALESCE(SUM(sub.filesize) ,0) AS objectsum
                      FROM (SELECT f.contenthash, MAX(f.filesize) AS filesize
                              FROM {files} f
                              LEFT JOIN {tool_objectfs_objects} o on f.contenthash = o.contenthash
                              GROUP BY f.contenthash, f.filesize, o.location
                              HAVING o.location = ?' . $localsql .') AS sub
                     WHERE sub.filesize != 0';

            $result = get_record_sql($sql, array($location));

            $result->datakey = $location;

            $report->add_row($result->datakey, $result->objectcount, $result->objectsum);

            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;
        }

        $report->add_row('total', $totalcount, $totalsum);

        return $report;
    }

}
