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

$id = required_param('id', PARAM_INT);   // Course.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);
$coursecontext = context_course::instance($course->id);
$url = '/report/grouptool/index.php';
$PAGE->set_url($url,['id' => $id]);
$PAGE->set_pagelayout('report');
$detail = optional_param('detail', '', PARAM_TEXT); // Show detailed info about one check only.

$url = '/report/grouptool/index.php';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_grouptool'));
echo 'Please Work';
echo $OUTPUT->footer();

