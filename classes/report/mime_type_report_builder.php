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

require_once(get_config('docroot') . 'module/objectfs/classes/report/objectfs_report_builder.php');

defined('INTERNAL') || die();

class mime_type_report_builder extends objectfs_report_builder {

    public function build_report() {

        $report = new objectfs_report('mime_type');

        $sql = "SELECT sum(size) as objectsum, filetype as datakey, count(*) as objectcount
                  FROM (SELECT size,
                  CASE
                      WHEN filetype = 'application/pdf'                                   THEN 'pdf'
                      WHEN filetype = 'application/epub+zip'                              THEN 'epub'
                      WHEN filetype =    'application/msword'                             THEN 'document'
                      WHEN filetype =    'application/x-mspublisher'                      THEN 'document'
                      WHEN filetype like 'application/vnd.ms-word%'                       THEN 'document'
                      WHEN filetype like 'application/vnd.oasis.opendocument.text%'       THEN 'document'
                      WHEN filetype like 'application/vnd.openxmlformats-officedocument%' THEN 'document'
                      WHEN filetype like 'application/vnd.ms-powerpoint%'                 THEN 'document'
                      WHEN filetype = 'application/vnd.oasis.opendocument.presentation'   THEN 'document'
                      WHEN filetype =    'application/vnd.oasis.opendocument.spreadsheet' THEN 'spreadsheet'
                      WHEN filetype like 'application/vnd.ms-excel%'                      THEN 'spreadsheet'
                      WHEN filetype =    'application/g-zip'                              THEN 'archive'
                      WHEN filetype =    'application/x-7z-compressed'                    THEN 'archive'
                      WHEN filetype =    'application/x-rar-compressed'                   THEN 'archive'
                      WHEN filetype like 'application/%'                                  THEN 'other'
                      ELSE         substr(filetype,0,position('/' IN filetype))
                   END AS filetype
                  FROM artefact_file_files
                 WHERE filetype IS NOT NULL AND size > 0) stats
              GROUP BY datakey
              ORDER BY sum(size) / 1024, datakey";

        $result = get_records_sql_array($sql);

        $report->add_rows($result);

        return $report;
    }
}
