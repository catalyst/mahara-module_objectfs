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

/**
 * Object location report builder.
 *
 * @package   module_objectfs
 * @author    Ilya Tregubov <ilya.tregubov@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

defined('INTERNAL') || die();

require_once($CFG->docroot . '/module/objectfs/classes/report/objectfs_report_builder.php');

class location_report_builder extends objectfs_report_builder {

    function __construct() {
    }

    public function build_report() {

        $locations = array(OBJECT_LOCATION_LOCAL,
                           OBJECT_LOCATION_DUPLICATED,
                           OBJECT_LOCATION_REMOTE,
                           OBJECT_LOCATION_ERROR);

        $totalcount = 0;
        $totalsum = 0;

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

            $sitedata['rows'][$key]['objectcount'] = $result->objectcount;
            $sitedata['rows'][$key]['objectsum'] = $result->objectsum;

            $totalcount += $result->objectcount;
            $totalsum += $result->objectsum;

        }

        $sitedata['rows']['total']['objectcount'] = $totalcount;
        $sitedata['rows']['total']['objectcount'] = $totalsum;

        $sitedata['reporttype'] = 'location';

        return $sitedata;
    }

}
