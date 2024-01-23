<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
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
 * Unit Tests for report/grouptool's download!
 *
 * @package    report_grouptool
 * @copyright  2023 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @author Anne Kreppenhofer <annek03@univie.ac.at> strongly based on mod_grouptools's privacy unit tests!
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_grouptool\local\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/report/grouptool/download.php');
require_once($CFG->dirroot . '/mod/grouptool/locallib.php');


/**
 * Unit Tests for report/grouptool's download! TODO: finish these unit tests here!
 * @group report_grouptool
 *
 * @copyright  2023 Academic Moodle Cooperation https://www.academic-moodle-cooperation.org/
 * @author Anne Kreppenhofer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_grouptool_download_testcase extends base {

    /**
     * Test that downloading a grouptool as excel works.
     *
     */
    public function test_download_excel() {
        self::markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * Test that downloading a grouptool as ods works.
     *
     */
    public function test_download_ods() {
        self::markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * Test that downloading a grouptool as txt works.
     *
     */
    public function test_download_txt() {
        self::markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * Test that downloading a grouptool as pdf works.
     *
     */
    public function test_download_pdf() {
        self::markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
    /**
     * Test that downloading a grouptool as pdf works.
     *
     */
    public function test_download_all_formats() {
        self::markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }


}
