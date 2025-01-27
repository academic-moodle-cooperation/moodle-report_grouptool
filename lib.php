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
 * This file contains the moodle hooks for the grouptool module.
 * @author Anne Kreppenhofer
 * @package report_grouptool
 * @copyright 2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This function extends the navigation with the report items
 * @package report_grouptool
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass        $course     The course to object for the report
 * @param context         $context    The context of the course
 */
function report_grouptool_extend_navigation_course($navigation, $course, $context) {
    global $PAGE, $OUTPUT, $USER;

    if (!has_capability('report/grouptool:view', context_course::instance($course->id), $USER->id)) {
        return;
    }
    $modinfo = get_fast_modinfo($course, -1);
    if (empty($modinfo->instances['grouptool'])) {
        $isthere = $navigation->get(get_string('grouptool', 'report_grouptool'));
        if ( $isthere != false) {
            $isthere->remove();
        }
        return;
    }

    $url = new moodle_url('/report/grouptool/index.php', ['id' => $course->id]);
    $node = navigation_node::create(get_string('pluginname', 'report_grouptool'), $url, navigation_node::TYPE_SETTING,
        null, null, new pix_icon('i/report', get_string('grouptool', 'report_grouptool')));
    $navigation->add_node($node);

}

/**
 * Adding Report Navigation
 * @package report_grouptool
 * @param global_navigation $nav
 * @return void
 */
function report_grouptool_extend_navigation(global_navigation $nav) {
    return;
}
