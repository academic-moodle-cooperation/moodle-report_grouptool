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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Report viewed event.
 *
 * @package   report_grouptool
 * @copyright 2026 Academic Moodle Cooperation
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_grouptool\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when the group management report is viewed.
 *
 * @package   report_grouptool
 * @copyright 2026 Academic Moodle Cooperation
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends \core\event\base {
    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventreportviewed', 'report_grouptool');
    }

    /**
     * Returns a description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' viewed the grouptool report " .
            "for the course with id '{$this->courseid}'.";
    }

    /**
     * Returns the URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url(
            '/report/grouptool/index.php',
            ['id' => $this->courseid]
        );
    }

    /**
     * Validates the event data.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();

        if ($this->contextlevel !== CONTEXT_COURSE) {
            throw new \coding_exception(
                'Context level must be CONTEXT_COURSE.'
            );
        }
    }
}
