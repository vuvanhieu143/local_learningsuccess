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
 * Explanation rule identifying missed or overdue course activities.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class missed_activity implements rule_interface {

    /**
     * Evaluate missed or overdue assignments.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    public function evaluate(int $userid, int $courseid): ?array {
        global $DB;

        $now = time();

        // Query assignments in course where duedate has passed and user has no submitted submission.
        $sql = "SELECT a.id, a.name, a.duedate
                  FROM {assign} a
             LEFT JOIN {assign_submission} s
                    ON s.assignment = a.id
                   AND s.userid = :userid
                   AND s.status = 'submitted'
                 WHERE a.course = :courseid
                   AND a.duedate > 0
                   AND a.duedate < :now
                   AND s.id IS NULL";

        $missed = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'now' => $now,
        ]);

        $count = count($missed);
        if ($count >= 1) {
            $severity = ($count >= 2) ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH;

            return [
                'type' => 'missed_activity',
                'severity' => $severity,
                'value' => $count,
                'message' => get_string('signal_missed_activities', 'local_learningsuccess', $count),
                'metadata' => [
                    'missed_count' => $count,
                    'assignments' => array_values(array_map(fn($a) => ['id' => $a->id, 'name' => $a->name], $missed)),
                ],
            ];
        }

        return null;
    }
}

