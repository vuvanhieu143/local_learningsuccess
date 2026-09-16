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

namespace local_learningsuccess\local\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralized student learning metrics query helper.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metrics_helper {

    /**
     * Fetch foundational learning metrics for an individual student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public static function get_student_metrics(int $userid, int $courseid): array {
        global $DB;

        $now = time();

        // 1. Inactivity.

        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        $inactivedays = $lastaccess ? (int) floor(($now - $lastaccess) / DAYSECS) : 14;

        // 2. Course grade.
        $graderecord = $DB->get_record_sql(
            "SELECT gg.finalgrade, gi.grademax
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.courseid = :courseid
                AND gi.itemtype = 'course'
                AND gg.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]
        );

        $gradepct = null;
        if ($graderecord && !is_null($graderecord->finalgrade) && (float)$graderecord->grademax > 0) {
            $gradepct = (float) round(($graderecord->finalgrade / (float)$graderecord->grademax) * 100, 2);
        }

        // 3. Module completion.
        $totalmodules = (int) $DB->count_records_select(
            'course_modules',
            'course = :courseid AND completion > 0 AND deletioninprogress = 0',
            ['courseid' => $courseid]
        );

        $completedmodules = 0;
        if ($totalmodules > 0) {
            $completedmodules = (int) $DB->count_records_sql(
                "SELECT COUNT(cmc.id)
                   FROM {course_modules_completion} cmc
                   JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  WHERE cm.course = :courseid
                    AND cmc.userid = :userid
                    AND cmc.completionstate IN (1, 2)",
                ['courseid' => $courseid, 'userid' => $userid]
            );
        }
        $completionpct = ($totalmodules > 0) ? (int) round(($completedmodules / $totalmodules) * 100) : 100;

        return [
            'lastaccess' => $lastaccess ? (int) $lastaccess : 0,
            'inactive_days' => $inactivedays,
            'gradepct' => $gradepct,
            'total_modules' => $totalmodules,
            'completed_modules' => $completedmodules,
            'completion_pct' => $completionpct,
        ];
    }
}
