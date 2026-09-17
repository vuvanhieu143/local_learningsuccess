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

    /**
     * Batch fetch foundational course metrics for multiple students in a single pass.
     * Results are memoized in-memory per course to prevent duplicate queries across providers.
     *
     * @param int[] $userids Target student user IDs
     * @param int $courseid Target course ID
     * @return array Pre-indexed batch records for enrolment, access, grade, completion, and activities
     */
    public static function get_course_metrics_batch(array $userids, int $courseid): array {
        global $DB;

        $userids = array_values(array_filter(array_unique(array_map('intval', $userids))));
        if (empty($userids)) {
            return [
                'course' => null,
                'coursestarted' => false,
                'enrolrecords' => [],
                'lastaccessrecords' => [],
                'totalmodules' => 0,
                'completionrecords' => [],
                'graderecords' => [],
                'quizattemptsrecords' => [],
                'totaloverdueassigns' => 0,
                'submittedrecords' => [],
            ];
        }

        $now = time();

        // 1. Course lifecycle.
        $course = $DB->get_record('course', ['id' => $courseid], 'id, startdate', MUST_EXIST);
        $coursestarted = ($course->startdate <= 0 || $course->startdate <= $now);

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $baseparams = array_merge(['courseid' => $courseid], $inparams);

        // 2. User enrolments.
        $enrolsql = "SELECT ue.userid, MIN(COALESCE(NULLIF(ue.timestart, 0), ue.timecreated)) AS enroltime
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE e.courseid = :courseid
                            AND ue.userid $insql
                   GROUP BY ue.userid";
        $enrolrecords = $DB->get_records_sql($enrolsql, $baseparams);

        // 3. Last access.
        $lastaccesssql = "SELECT userid, timeaccess
                            FROM {user_lastaccess}
                           WHERE courseid = :courseid
                                 AND userid $insql";
        $lastaccessrecords = $DB->get_records_sql($lastaccesssql, $baseparams);

        // 4. Module completion.
        $totalmodules = (int) $DB->count_records_select(
            'course_modules',
            'course = :courseid AND completion > 0 AND deletioninprogress = 0',
            ['courseid' => $courseid]
        );

        $completionrecords = [];
        if ($totalmodules > 0) {
            $cmcsql = "SELECT cmc.userid, COUNT(cmc.id) AS completed_count
                         FROM {course_modules_completion} cmc
                         JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = :courseid
                              AND cmc.completionstate IN (1, 2)
                              AND cmc.userid $insql
                     GROUP BY cmc.userid";
            $completionrecords = $DB->get_records_sql($cmcsql, $baseparams);
        }

        // 5. Final grades.
        $gradesql = "SELECT gg.userid, gg.finalgrade, gi.grademax
                       FROM {grade_grades} gg
                       JOIN {grade_items} gi ON gi.id = gg.itemid
                      WHERE gi.courseid = :courseid
                            AND gi.itemtype = 'course'
                            AND gg.userid $insql";
        $graderecords = $DB->get_records_sql($gradesql, $baseparams);

        // 6. Quiz attempts.
        $quizattemptsrecords = [];
        if ($DB->record_exists('quiz', ['course' => $courseid])) {
            $quizsql = "SELECT qa.userid, COUNT(qa.id) AS total_attempts, MAX(qa.attempt) AS max_attempt
                          FROM {quiz_attempts} qa
                          JOIN {quiz} q ON q.id = qa.quiz
                         WHERE q.course = :courseid
                               AND qa.state = 'finished'
                               AND qa.userid $insql
                      GROUP BY qa.userid";
            $quizattemptsrecords = $DB->get_records_sql($quizsql, $baseparams);
        }

        // 7. Overdue assignment submissions.
        $totaloverdueassigns = (int) $DB->count_records_select(
            'assign',
            'course = :courseid AND duedate > 0 AND duedate < :now',
            ['courseid' => $courseid, 'now' => $now]
        );
        $submittedrecords = [];
        if ($totaloverdueassigns > 0) {
            $submitsql = "SELECT s.userid, COUNT(s.id) AS submitted_count
                            FROM {assign_submission} s
                            JOIN {assign} a ON a.id = s.assignment
                           WHERE a.course = :courseid
                                 AND a.duedate > 0
                                 AND a.duedate < :now
                                 AND s.status = 'submitted'
                                 AND s.userid $insql
                        GROUP BY s.userid";
            $submittedrecords = $DB->get_records_sql($submitsql, $baseparams);
        }

        $batchdata = [
            'course' => $course,
            'coursestarted' => $coursestarted,
            'enrolrecords' => $enrolrecords,
            'lastaccessrecords' => $lastaccessrecords,
            'totalmodules' => $totalmodules,
            'completionrecords' => $completionrecords,
            'graderecords' => $graderecords,
            'quizattemptsrecords' => $quizattemptsrecords,
            'totaloverdueassigns' => $totaloverdueassigns,
            'submittedrecords' => $submittedrecords,
        ];

        return $batchdata;
    }
}
