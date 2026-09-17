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

namespace local_learningsuccess\local\signal;

use local_learningsuccess\local\explanation\explanation;

/**
 * Signal evaluator detecting overdue assignment submissions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overdue_signal implements signal {
    /** @var string Signal type identifier. */
    public const TYPE = 'overdue';

    /**
     * Evaluate overdue activities for student.
     *
     * @param int $userid
     * @param int $courseid
     * @return explanation|null
     */
    public function evaluate(int $userid, int $courseid): ?explanation {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('assign')) {
            return null;
        }

        $now = time();
        $sql = "SELECT a.id, a.name, a.duedate
                  FROM {assign} a
                 WHERE a.course = :courseid
                       AND a.duedate > 0
                       AND a.duedate < :now
                       AND NOT EXISTS (
                           SELECT 1
                             FROM {assign_submission} s
                            WHERE s.assignment = a.id
                                  AND s.userid = :userid
                                  AND s.status = 'submitted'
                       )";

        $overdue = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'now' => $now,
            'userid' => $userid,
        ]);

        $count = count($overdue);
        if ($count === 0) {
            return null;
        }

        $names = array_slice(array_map(fn($item) => $item->name, $overdue), 0, 3);
        $severity = ($count >= 2) ? explanation::SEVERITY_CRITICAL : explanation::SEVERITY_WARNING;

        return new explanation(
            type: self::TYPE,
            severity: $severity,
            title: get_string('signal_overdue_title', 'local_learningsuccess'),
            description: get_string('signal_overdue_desc', 'local_learningsuccess', $count),
            value: $count,
            evidence: [
                'overdue_count' => $count,
                'sample_activities' => $names,
            ]
        );
    }
}
