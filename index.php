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
 * Grouptool report
 *
 * @package    report_grouptool
 * @author     Anne Kreppenhofer
 * @copyright  2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;
require('../../config.php');
require_once($CFG->dirroot.'/report/grouptool/locallib.php');
require_once($CFG->dirroot.'/report/grouptool/lib.php');

$id = required_param('id', PARAM_INT);   // Course.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$coursecontext = context_course::instance($course->id);
require_capability('report/grouptool:view', $coursecontext);

$url = '/report/grouptool/index.php';
$PAGE->set_url($url, ['id' => $id]);
$PAGE->set_pagelayout('report');
$detail = optional_param('detail', '', PARAM_TEXT); // Show detailed info about one check only.

if (!$grouptools = get_all_instances_in_course('grouptool', $course)) {
    notice(get_string('nogrouptools', 'report_grouptool'), new moodle_url('/course/view.php', ['id' => $course->id]));
}
echo $OUTPUT->header();
report_helper::print_report_selector(get_string('pluginname', 'report_grouptool'));

if (!isset($SESSION->report_grouptool)) {
    $SESSION->report_grouptool = new stdClass();
}
foreach ($grouptools as $grouptool) {
    $report = new report_grouptool($grouptool->coursemodule, $grouptool, null, $course);
    echo $OUTPUT->heading($OUTPUT->box(format_string($grouptool->name)),['href'=>'#']);
    $report->view_userlist();
}

echo $OUTPUT->footer();

