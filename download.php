<?php
// This file is part of report_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handles download of userview and course overview in various formats
 *
 * @package   report_grouptool
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_once($CFG->dirroot . '/report/grouptool/locallib.php');

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('grouptool', $cmid);
$context = context_module::instance($cmid);
$PAGE->set_context($context);
$url = new moodle_url($CFG->wwwroot . '/report/grouptool/download.php', ['id' => $cmid]);
$PAGE->set_url($url);
$instance = new report_grouptool($cmid);

require_login($cm->course, true, $cm);
require_capability('report/grouptool:export', $context);

$groupingid = optional_param('groupingid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$includeinactive = optional_param('inactive', 0, PARAM_BOOL);
$PAGE->url->param('groupingid', $groupingid);
$PAGE->url->param('groupid', $groupid);
$PAGE->url->param('inactive', $includeinactive);

$modinfo = get_fast_modinfo($cm->course);
$cm = $modinfo->get_cm($cm->id);
if (empty($cm->uservisible)) {
    if ($cm->availableinfo) {
        // User cannot access the activity, but on the course page they will!
        // see a link to it, greyed-out, with information (HTML format) from!
        // $cm->availableinfo about why they can't access it.
        $text = html_writer::empty_tag('br') . $cm->availableinfo;
    } else {
        // User cannot access the activity and they will not see it at all.
        $text = '';
    }
    $notification = $OUTPUT->notification(get_string('condition_prevent_access', 'report_grouptool') .
                                          html_writer::empty_tag('br') . $text, 'error');
    echo $OUTPUT->header();
    echo $OUTPUT->box($notification, 'generalbox centered');
    echo $OUTPUT->footer();
    die;
}

$format = required_param('format', PARAM_INT);
switch ($format) {
    case GROUPTOOL_PDF:
        $readableformat = 'PDF';
        break;
    case GROUPTOOL_TXT:
        $readableformat = 'TXT';
        break;
    case GROUPTOOL_XLSX:
        $readableformat = 'XLSX';
        break;
    case GROUPTOOL_ODS:
        $readableformat = 'ODS';
        break;
    default:
        $readableformat = 'unknown';
}

// Trigger userlist event!
$event = \report_grouptool\event\userlist_exported::create([
    'objectid' => $cm->instance,
    'context'  => context_module::instance($cm->id),
    'other'    => [
        'format_readable' => $readableformat,
        'format' => $format,
        'groupid' => $groupid,
        'groupingid' => $groupingid,
    ],
]);
$event->trigger();
switch ($format) {
    case GROUPTOOL_PDF:
        $PAGE->url->param('format', GROUPTOOL_PDF);
        $instance->download_userlist_pdf($groupid, $groupingid);
        break;
    case GROUPTOOL_TXT:
        $PAGE->url->param('format', GROUPTOOL_TXT);
        $instance->download_userlist_txt($groupid, $groupingid);
        break;
    case GROUPTOOL_XLSX:
        $PAGE->url->param('format', GROUPTOOL_XLSX);
        $instance->download_userlist_xlsx($groupid, $groupingid);
        break;
    case GROUPTOOL_ODS:
        $PAGE->url->param('format', GROUPTOOL_ODS);
        $instance->download_userlist_ods($groupid, $groupingid);
        break;
    default:
        break;
}
