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

namespace local_learningsuccess\local\explanation\rules;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\explanation\rule_interface;

/**
 * Explanation rule evaluating student inactivity in a course.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactivity implements rule_interface {

    /**
     * Evaluate student inactivity.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    public function evaluate(int $userid, int $courseid): ?array {
        global $DB;

        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        $threshold = (int) get_config('local_learningsuccess', 'inactivity_threshold') ?: 7;
        $now = time();

        if (!$lastaccess) {
            // User has never accessed the course.
            return [
                'type' => 'inactivity',
                'severity' => self::SEVERITY_CRITICAL,
                'value' => 30,
                'message' => get_string('signal_inactivity_critical', 'local_learningsuccess', 30),
                'metadata' => ['never_accessed' => true, 'threshold' => $threshold],
            ];
        }

        $inactivedays = (int) floor(($now - $lastaccess) / DAYSECS);

        if ($inactivedays >= $threshold * 2) {
            return [
                'type' => 'inactivity',
                'severity' => self::SEVERITY_CRITICAL,
                'value' => $inactivedays,
                'message' => get_string('signal_inactivity_critical', 'local_learningsuccess', $inactivedays),
                'metadata' => ['days' => $inactivedays, 'threshold' => $threshold],
            ];
        } else if ($inactivedays >= $threshold) {
            return [
                'type' => 'inactivity',
                'severity' => self::SEVERITY_HIGH,
                'value' => $inactivedays,
                'message' => get_string('signal_inactivity_warning', 'local_learningsuccess', $inactivedays),
                'metadata' => ['days' => $inactivedays, 'threshold' => $threshold],
            ];
        }

        return null;
    }
}

