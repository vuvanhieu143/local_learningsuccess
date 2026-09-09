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
 * Explanation rule evaluating low course completion.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion implements rule_interface {

    /**
     * Evaluate completion progress.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    public function evaluate(int $userid, int $courseid): ?array {
        global $DB;

        // Query total trackable modules in the course.
        $totalmodules = $DB->count_records_sql(
            "SELECT COUNT(cm.id)
               FROM {course_modules} cm
              WHERE cm.course = :courseid
                AND cm.completion > 0
                AND cm.deletioninprogress = 0",
            ['courseid' => $courseid]
        );

        if ($totalmodules === 0) {
            return null;
        }

        // Query modules completed by student.
        $completedmodules = $DB->count_records_sql(
            "SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :courseid
                AND cmc.userid = :userid
                AND cmc.completionstate IN (1, 2)", // 1 = COMPLETE, 2 = COMPLETE_PASS.
            ['courseid' => $courseid, 'userid' => $userid]
        );

        $percentage = (int) round(($completedmodules / $totalmodules) * 100);

        if ($percentage < 30) {
            return [
                'type' => 'completion',
                'severity' => self::SEVERITY_HIGH,
                'value' => $percentage,
                'message' => get_string('signal_completion_low', 'local_learningsuccess', $percentage),
                'metadata' => [
                    'completed' => $completedmodules,
                    'total' => $totalmodules,
                    'percentage' => $percentage,
                ],
            ];
        } else if ($percentage < 50) {
            return [
                'type' => 'completion',
                'severity' => self::SEVERITY_MEDIUM,
                'value' => $percentage,
                'message' => get_string('signal_completion_low', 'local_learningsuccess', $percentage),
                'metadata' => [
                    'completed' => $completedmodules,
                    'total' => $totalmodules,
                    'percentage' => $percentage,
                ],
            ];
        }

        return null;
    }
}

