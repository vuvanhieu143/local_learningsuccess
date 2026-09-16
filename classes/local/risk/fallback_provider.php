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

namespace local_learningsuccess\local\risk;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\helper\metrics_helper;

/**
 * Fallback risk provider using observable course activity and academic signals.
 *
 * Clearly labeled as deterministic prioritisation scoring, not calibrated machine learning probabilities.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fallback_provider implements risk_provider {

    public const SOURCE_NAME = 'course_activity_signals';

    /**
     * Compute deterministic prioritisation risk score based on course activity and performance.
     *
     * @param int $userid Target student user ID.
     * @param int $courseid Target course ID.
     * @return risk_result
     */
    public function get_risk(int $userid, int $courseid): risk_result {
        $risks = $this->get_risks([$userid], $courseid);
        return $risks[$userid] ?? new risk_result(0.0, risk_result::LEVEL_HEALTHY, self::SOURCE_NAME);
    }

    /**
     * Bulk compute deterministic prioritisation risk scores for multiple students in a course.
     *
     * Uses $O(1)$ batch database queries while maintaining identical risk scoring logic as get_risk().
     *
     * @param int[] $userids Array of target student user IDs.
     * @param int $courseid Target course ID.
     * @return array<int, risk_result> Map of userid => risk_result.
     */
    public function get_risks(array $userids, int $courseid): array {
        $userids = array_values(array_filter(array_unique(array_map('intval', $userids))));
        if (empty($userids)) {
            return [];
        }

        $now = time();
        $batch = metrics_helper::get_course_metrics_batch($userids, $courseid);

        $course = $batch['course'];
        $coursestarted = $batch['coursestarted'];
        $enrolrecords = $batch['enrolrecords'];
        $lastaccessrecords = $batch['lastaccessrecords'];
        $totalmodules = $batch['totalmodules'];
        $completioncounts = $batch['completionrecords'];
        $graderecords = $batch['graderecords'];

        // Compute risk scoring for each student.
        $results = [];
        $graceperiod = 5 * DAYSECS;

        foreach ($userids as $uid) {
            $riskscore = 0.0;
            $hasmeaningfuldata = false;

            $enroltime = isset($enrolrecords[$uid]) ? (int) $enrolrecords[$uid]->enroltime : $now;
            $isrecentlyenrolled = ($now - $enroltime) < $graceperiod;

            // Inactivity evaluation.
            $lastaccess = isset($lastaccessrecords[$uid]) ? (int) $lastaccessrecords[$uid]->timeaccess : 0;
            if ($lastaccess > 0) {
                $hasmeaningfuldata = true;
                $inactivedays = (int) floor(($now - $lastaccess) / DAYSECS);
            } else if (!$coursestarted) {
                // Course hasn't started yet: no inactivity penalty.
                $inactivedays = 0;
            } else if ($isrecentlyenrolled) {
                // Student enrolled recently: calculate days since enrolment or startdate.
                $effectivebaseline = max($enroltime, (int) $course->startdate);
                $inactivedays = (int) floor(max(0, $now - $effectivebaseline) / DAYSECS);
            } else {
                // Enrolled well in the past on an active course without ever accessing.
                $effectivebaseline = max($enroltime, (int) $course->startdate);
                $inactivedays = (int) floor(max(0, $now - $effectivebaseline) / DAYSECS);
                if ($inactivedays < 14) {
                    $inactivedays = max(14, $inactivedays);
                }
            }

            // Inactivity score points (suppressed during first 3 days of enrolment).
            if ($coursestarted && (!$isrecentlyenrolled || $inactivedays >= 3)) {
                if ($inactivedays >= 14) {
                    $riskscore += 45.0;
                } else if ($inactivedays >= 7) {
                    $riskscore += 30.0;
                } else if ($inactivedays >= 4) {
                    $riskscore += 15.0;
                }
            }

            // Grade evaluation.
            if (isset($graderecords[$uid]) && $graderecords[$uid]->finalgrade !== null && (float) $graderecords[$uid]->grademax > 0) {
                $hasmeaningfuldata = true;
                $pct = round(((float) $graderecords[$uid]->finalgrade / (float) $graderecords[$uid]->grademax) * 100.0, 1);
                if ($pct < 40.0) {
                    $riskscore += 40.0;
                } else if ($pct < 60.0) {
                    $riskscore += 25.0;
                } else if ($pct < 75.0) {
                    $riskscore += 10.0;
                }
            }

            // Completion evaluation (suppressed if course hasn't started or student is in grace period).
            if ($totalmodules > 0) {
                $completed = isset($completioncounts[$uid])
                    ? (int) ($completioncounts[$uid]->completed_count ?? $completioncounts[$uid]->completedcount ?? 0)
                    : 0;
                if ($completed > 0) {
                    $hasmeaningfuldata = true;
                }
                if ($coursestarted && !$isrecentlyenrolled) {
                    $completionpct = round(($completed / $totalmodules) * 100.0, 1);
                    if ($completionpct < 25.0) {
                        $riskscore += 20.0;
                    } else if ($completionpct < 50.0) {
                        $riskscore += 10.0;
                    }
                }
            }

            // Handle "No Data" state (Issue 5 & 11):
            // When there is no course activity, no grades, and course hasn't started or student enrolled recently.
            if (!$hasmeaningfuldata && (!$coursestarted || $isrecentlyenrolled)) {
                $results[$uid] = new risk_result(
                    score: 0.0,
                    level: risk_result::LEVEL_NO_DATA,
                    source: self::SOURCE_NAME,
                    model: null
                );
                continue;
            }

            // Clamp priority score between 0 and 100.
            $riskscore = min(100.0, max(0.0, $riskscore));

            // Map to normalized risk levels.
            $level = risk_result::LEVEL_HEALTHY;
            if ($riskscore >= 70.0) {
                $level = risk_result::LEVEL_CRITICAL;
            } else if ($riskscore >= 50.0) {
                $level = risk_result::LEVEL_ATRISK;
            } else if ($riskscore >= 25.0) {
                $level = risk_result::LEVEL_MONITOR;
            }

            $results[$uid] = new risk_result(
                score: $riskscore,
                level: $level,
                source: self::SOURCE_NAME,
                model: null
            );
        }

        return $results;
    }
}
