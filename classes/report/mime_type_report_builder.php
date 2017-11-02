<?php
/**
 * Mime type report
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace module_objectfs\report;

require_once($CFG->docroot . 'module/objectfs/classes/report/objectfs_report_builder.php');

defined('INTERNAL') || die();

class mime_type_report_builder extends objectfs_report_builder {

    public function build_report() {

        $report = new objectfs_report('mime_type');

        $sql = "SELECT sum(filesize) as objectsum, filetype as datakey, count(*) as objectcount
                FROM (SELECT distinct filesize,
                        CASE
                            WHEN mimetype = 'application/pdf'                                   THEN 'pdf'
                            WHEN mimetype = 'application/epub+zip'                              THEN 'epub'
                            WHEN mimetype = 'application/vnd.moodle.backup'                     THEN 'moodlebackup'
                            WHEN mimetype =    'application/msword'                             THEN 'document'
                            WHEN mimetype =    'application/x-mspublisher'                      THEN 'document'
                            WHEN mimetype like 'application/vnd.ms-word%'                       THEN 'document'
                            WHEN mimetype like 'application/vnd.oasis.opendocument.text%'       THEN 'document'
                            WHEN mimetype like 'application/vnd.openxmlformats-officedocument%' THEN 'document'
                            WHEN mimetype like 'application/vnd.ms-powerpoint%'                 THEN 'document'
                            WHEN mimetype = 'application/vnd.oasis.opendocument.presentation'   THEN 'document'
                            WHEN mimetype =    'application/vnd.oasis.opendocument.spreadsheet' THEN 'spreadsheet'
                            WHEN mimetype like 'application/vnd.ms-excel%'                      THEN 'spreadsheet'
                            WHEN mimetype =    'application/g-zip'                              THEN 'archive'
                            WHEN mimetype =    'application/x-7z-compressed'                    THEN 'archive'
                            WHEN mimetype =    'application/x-rar-compressed'                   THEN 'archive'
                            WHEN mimetype like 'application/%'                                  THEN 'other'
                            ELSE         substr(mimetype,0,position('/' IN mimetype))
                        END AS filetype
                        FROM {files}
                        WHERE mimetype IS NOT NULL) stats
                GROUP BY datakey
                ORDER BY
                sum(filesize) / 1024, datakey";

        $result = get_records_sql_array($sql);

        $report->add_rows($result);

        return $report;
    }
}
