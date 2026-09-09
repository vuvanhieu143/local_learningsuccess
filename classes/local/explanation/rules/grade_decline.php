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
 * Explanation rule identifying sharp grade declines between assessments.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_decline implements rule_interface {

    /**
     * Evaluate grade variance.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    public function evaluate(int $userid, int $courseid): ?array {
        global $DB;

        // Fetch chronological graded items for user.
        $sql = "SELECT gg.id, gg.finalgrade, gg.timemodified, gi.grademax, gi.itemname
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = 'mod'
                   AND gg.userid = :userid
                   AND gg.finalgrade IS NOT NULL
                   AND gi.grademax > 0
              ORDER BY gg.timemodified ASC";

        $grades = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        if (count($grades) < 2) {
            return null;
        }

        $scores = [];
        foreach ($grades as $g) {
            $scores[] = ($g->finalgrade / $g->grademax) * 100;
        }

        $count = count($scores);
        $previous = $scores[$count - 2];
        $latest = $scores[$count - 1];

        $threshold = (int) get_config('local_learningsuccess', 'grade_decline_threshold') ?: 20;
        $drop = round($previous - $latest);

        if ($drop >= $threshold) {
            $severity = ($drop >= $threshold * 1.5) ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH;

            return [
                'type' => 'grade_decline',
                'severity' => $severity,
                'value' => (int) $drop,
                'message' => get_string('signal_grade_decline', 'local_learningsuccess', $drop),
                'metadata' => [
                    'previous_score' => round($previous, 1),
                    'latest_score' => round($latest, 1),
                    'drop_percentage' => $drop,
                ],
            ];
        }

        return null;
    }
}

