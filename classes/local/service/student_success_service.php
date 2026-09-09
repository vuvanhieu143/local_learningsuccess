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

namespace local_learningsuccess\local\service;

defined('MOODLE_INTERNAL') || die();

use cache;
use local_learningsuccess\local\analytics\analytics_adapter;
use local_learningsuccess\local\explanation\explanation_engine;
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\recommendation\recommendation_engine;

/**
 * Application service assembling high-performance views for teachers and course dashboards.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_success_service {

    protected explanation_engine $explanationengine;
    protected recommendation_engine $recommendationengine;
    protected intervention_manager $interventionmanager;

    public function __construct(
        ?explanation_engine $explanationengine = null,
        ?recommendation_engine $recommendationengine = null,
        ?intervention_manager $interventionmanager = null
    ) {
        $this->explanationengine = $explanationengine ?? new explanation_engine();
        $this->recommendationengine = $recommendationengine ?? new recommendation_engine();
        $this->interventionmanager = $interventionmanager ?? new intervention_manager();
    }

    /**
     * Get aggregated course pulse and health summary.
     *
     * @param int $courseid
     * @param int $groupid Optional group ID to filter by
     * @param bool $skipcache
     * @return array
     */
    public function get_course_summary(int $courseid, int $groupid = 0, bool $skipcache = false): array {
        global $DB;

        $cache = cache::make('local_learningsuccess', 'course_summary');
        $cachekey = $groupid > 0 ? "{$courseid}_{$groupid}" : $courseid;
        if (!$skipcache) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch enrolled students in course (or group).
        $context = \context_course::instance($courseid);
        $enrolledusers = get_enrolled_users($context, 'moodle/course:isincompletionreports', $groupid, 'u.id, u.firstname, u.lastname');
        if (empty($enrolledusers)) {
            // Fallback to all enrolled learners if completion capability is not explicitly assigned.
            $enrolledusers = get_enrolled_users($context, '', $groupid, 'u.id, u.firstname, u.lastname');
        }

        $total = count($enrolledusers);
        $counts = [
            analytics_adapter::STATUS_HEALTHY => 0,
            analytics_adapter::STATUS_MONITOR => 0,
            analytics_adapter::STATUS_ATRISK => 0,
            analytics_adapter::STATUS_CRITICAL => 0,
        ];

        $studentprofiles = $this->batch_explain_students($enrolledusers, $courseid);
        foreach ($studentprofiles as $profile) {
            $counts[$profile['status']]++;
        }

        // Active and resolved interventions.
        $activeinterventions = $DB->count_records_select(
            'local_ls_intervention',
            'courseid = :courseid AND status IN (:open, :inprogress)',
            [
                'courseid' => $courseid,
                'open' => intervention_manager::STATUS_OPEN,
                'inprogress' => intervention_manager::STATUS_IN_PROGRESS,
            ]
        );

        $resolvedinterventions = $DB->count_records(
            'local_ls_intervention',
            [
                'courseid' => $courseid,
                'status' => intervention_manager::STATUS_COMPLETED,
                'outcome' => 'IMPROVED',
            ]
        );

        $result = [
            'courseid' => $courseid,
            'total_students' => $total,
            'healthy_count' => $counts[analytics_adapter::STATUS_HEALTHY],
            'monitor_count' => $counts[analytics_adapter::STATUS_MONITOR],
            'atrisk_count' => $counts[analytics_adapter::STATUS_ATRISK],
            'critical_count' => $counts[analytics_adapter::STATUS_CRITICAL],
            'active_interventions' => $activeinterventions,
            'resolved_interventions' => $resolvedinterventions,
            'students' => $studentprofiles,
            'timestamp' => time(),
        ];

        $cache->set($cachekey, $result);

        return $result;
    }

    /**
     * Get detailed student profile including explanations, recommendations, and past interventions.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $skipcache
     * @return array
     */
    public function get_student_summary(int $userid, int $courseid, bool $skipcache = false): array {
        global $DB;

        $cache = cache::make('local_learningsuccess', 'student_summary');
        $cachekey = "{$courseid}_{$userid}";
        if (!$skipcache) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', MUST_EXIST);
        $explained = $this->explanationengine->explain_student($userid, $courseid);
        $recommendations = $this->recommendationengine->recommend($explained['signals']);
        $interventions = $this->interventionmanager->get_for_student($userid, $courseid);

        // Fetch notes and due follow-ups for student interventions.
        $allnotes = [];
        $pendingfollowups = [];
        foreach ($interventions as $inv) {
            $notes = $this->interventionmanager->get_notes((int) $inv->id);
            if (!empty($notes)) {
                $allnotes[$inv->id] = array_values($notes);
            }
            if (!empty($inv->followupat) && (int) $inv->followupat <= time()) {
                $pendingfollowups[] = $inv;
            }
        }

        $summary = new student_summary(
            userid: $userid,
            courseid: $courseid,
            user: [
                'id' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
            ],
            risk: [
                'score' => $explained['risk_score'],
                'level' => $explained['status'],
                'source' => $explained['source'] ?? 'course_activity_signals',
            ],
            signals: $explained['signals'],
            explanations: $explained['signals'],
            recommendations: $recommendations,
            interventions: array_values($interventions),
            pendingfollowups: $pendingfollowups,
            notes: $allnotes
        );

        $result = $summary->to_array();
        $cache->set($cachekey, $result);

        return $result;
    }

    /**
     * Retrieve recent successful interventions where the student improved.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public function get_recent_success_stories(int $courseid, int $limit = 5): array {
        global $DB;

        $sql = "SELECT i.id, i.userid, i.type, i.actual_action, i.completed_at, i.timemodified,
                       u.firstname, u.lastname
                  FROM {local_ls_intervention} i
                  JOIN {user} u ON u.id = i.userid
                 WHERE i.courseid = :courseid
                   AND i.status = :completed
                   AND i.outcome = :improved
              ORDER BY COALESCE(i.completed_at, i.timemodified) DESC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'completed' => intervention_manager::STATUS_COMPLETED,
            'improved' => 'IMPROVED',
        ], 0, $limit);

        $stories = [];
        foreach ($records as $r) {
            $completedtime = $r->completed_at ? (int) $r->completed_at : (int) $r->timemodified;
            $typekey = 'type_' . strtolower($r->type);
            $stories[] = [
                'id' => (int) $r->id,
                'userid' => (int) $r->userid,
                'fullname' => fullname($r),
                'type' => $r->type,
                'type_label' => get_string($typekey, 'local_learningsuccess'),
                'action_note' => $r->actual_action,
                'completed_date' => userdate($completedtime, get_string('strftimedatemonthabbr', 'langconfig')),
            ];
        }

        return $stories;
    }

    /**
     * Retrieve prioritised queue of students requiring immediate attention.
     *
     * @param int $courseid
     * @param int $groupid Optional group ID filter
     * @param int $limit
     * @return array
     */
    public function get_priority_students(int $courseid, int $groupid = 0, int $limit = 10): array {
        $summary = $this->get_course_summary($courseid, $groupid);
        $students = $summary['students'];

        // Filter for students who are not healthy.
        $needsattention = array_filter(
            $students,
            fn($s) => in_array($s['status'], [analytics_adapter::STATUS_CRITICAL, analytics_adapter::STATUS_ATRISK, analytics_adapter::STATUS_MONITOR])
        );

        // Sort descending by risk score, then signal count.
        usort($needsattention, function ($a, $b) {
            if ($a['risk_score'] === $b['risk_score']) {
                return $b['signal_count'] <=> $a['signal_count'];
            }
            return $b['risk_score'] <=> $a['risk_score'];
        });

        return array_values(array_slice($needsattention, 0, $limit));
    }

    /**
     * Batch process and explain multiple students using high-performance bulk SQL queries.
     * Reduces O(N) database queries down to O(1).
     *
     * @param array $enrolledusers Array of enrolled user records
     * @param int $courseid Target course ID
     * @return array Array of explained student profiles
     */
    public function batch_explain_students(array $enrolledusers, int $courseid): array {
        global $DB;

        if (empty($enrolledusers)) {
            return [];
        }

        $userids = array_map(fn($u) => (int) $u->id, array_values($enrolledusers));
        $now = time();
        $inactivitywarning = (int) get_config('local_learningsuccess', 'inactivity_threshold') ?: 7;
        $inactivitycritical = $inactivitywarning * 2;

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $baseparams = array_merge(['courseid' => $courseid], $inparams);

        // 1. Bulk query last access.
        $lastaccesssql = "SELECT userid, timeaccess FROM {user_lastaccess} WHERE courseid = :courseid AND userid $insql";
        $lastaccessrecords = $DB->get_records_sql($lastaccesssql, $baseparams);

        // 2. Bulk query module completion count.
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
                        WHERE cm.course = :courseid AND cmc.completionstate IN (1, 2)
                          AND cmc.userid $insql
                     GROUP BY cmc.userid";
            $completionrecords = $DB->get_records_sql($cmcsql, $baseparams);
        }

        // 3. Bulk query course final grades.
        $gradesql = "SELECT gg.userid, gg.finalgrade, gi.grademax
                       FROM {grade_grades} gg
                       JOIN {grade_items} gi ON gi.id = gg.itemid
                      WHERE gi.courseid = :courseid AND gi.itemtype = 'course'
                        AND gg.userid $insql";
        $graderecords = $DB->get_records_sql($gradesql, $baseparams);

        // 4. Bulk query quiz attempts (find repeated attempts / struggles).
        $quizattemptsrecords = [];
        if ($DB->record_exists('quiz', ['course' => $courseid])) {
            $quizsql = "SELECT qa.userid, COUNT(qa.id) AS total_attempts, MAX(qa.attempt) AS max_attempt
                          FROM {quiz_attempts} qa
                          JOIN {quiz} q ON q.id = qa.quiz
                         WHERE q.course = :courseid AND qa.state = 'finished'
                           AND qa.userid $insql
                      GROUP BY qa.userid";
            $quizattemptsrecords = $DB->get_records_sql($quizsql, $baseparams);
        }

        // 5. Bulk query submitted assignments for overdue evaluation.
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
                           WHERE a.course = :courseid AND a.duedate > 0 AND a.duedate < :now
                             AND s.status = 'submitted' AND s.userid $insql
                        GROUP BY s.userid";
            $submittedrecords = $DB->get_records_sql($submitsql, $baseparams);
        }

        $results = [];

        foreach ($enrolledusers as $user) {
            $uid = (int) $user->id;
            $signals = [];
            $riskscore = 0;
            $percent = null;
            $rate = 100;

            // Inactivity rule.
            $lastaccess = isset($lastaccessrecords[$uid]) ? (int) $lastaccessrecords[$uid]->timeaccess : 0;
            if ($lastaccess === 0) {
                $daysinactive = 999;
                $signals[] = [
                    'rule' => 'inactivity',
                    'severity' => 'critical',
                    'message' => get_string('signal_inactivity_critical', 'local_learningsuccess', '14+'),
                    'value' => 14,
                ];
                $riskscore += 40;
            } else {
                $daysinactive = (int) floor(($now - $lastaccess) / DAYSECS);
                if ($daysinactive >= $inactivitycritical) {
                    $signals[] = [
                        'rule' => 'inactivity',
                        'severity' => 'critical',
                        'message' => get_string('signal_inactivity_critical', 'local_learningsuccess', $daysinactive),
                        'value' => $daysinactive,
                    ];
                    $riskscore += 40;
                } else if ($daysinactive >= $inactivitywarning) {
                    $signals[] = [
                        'rule' => 'inactivity',
                        'severity' => 'warning',
                        'message' => get_string('signal_inactivity_warning', 'local_learningsuccess', $daysinactive),
                        'value' => $daysinactive,
                    ];
                    $riskscore += 25;
                }
            }

            // Completion rule.
            if ($totalmodules > 0) {
                $completed = isset($completionrecords[$uid]) ? (int) $completionrecords[$uid]->completed_count : 0;
                $rate = (int) round(($completed / $totalmodules) * 100);
                if ($rate < 30) {
                    $signals[] = [
                        'rule' => 'completion',
                        'severity' => 'critical',
                        'message' => get_string('signal_completion_low', 'local_learningsuccess', $rate),
                        'value' => $rate,
                    ];
                    $riskscore += 35;
                } else if ($rate < 60) {
                    $signals[] = [
                        'rule' => 'completion',
                        'severity' => 'warning',
                        'message' => get_string('signal_completion_low', 'local_learningsuccess', $rate),
                        'value' => $rate,
                    ];
                    $riskscore += 20;
                }
            }

            // Grade performance rule.
            if (isset($graderecords[$uid]) && $graderecords[$uid]->grademax > 0 && $graderecords[$uid]->finalgrade !== null) {
                $percent = (int) round(($graderecords[$uid]->finalgrade / $graderecords[$uid]->grademax) * 100);
                if ($percent < 40) {
                    $signals[] = [
                        'rule' => 'grade_performance',
                        'severity' => 'critical',
                        'message' => get_string('signal_quiz_low', 'local_learningsuccess', $percent),
                        'value' => $percent,
                    ];
                    $riskscore += 30;
                } else if ($percent < 50) {
                    $signals[] = [
                        'rule' => 'grade_performance',
                        'severity' => 'warning',
                        'message' => get_string('signal_quiz_low', 'local_learningsuccess', $percent),
                        'value' => $percent,
                    ];
                    $riskscore += 15;
                }
            }

            // Quiz Struggle (repeated retries).
            $maxattempt = isset($quizattemptsrecords[$uid]) ? (int) $quizattemptsrecords[$uid]->max_attempt : 0;
            if ($maxattempt >= 2) {
                $signals[] = [
                    'rule' => 'quiz_retries',
                    'severity' => 'warning',
                    'message' => get_string('signal_quiz_retries', 'local_learningsuccess', $maxattempt),
                    'value' => $maxattempt,
                ];
                $riskscore += 20;
            }

            // Overdue assignments.
            $missedassigns = 0;
            if ($totaloverdueassigns > 0) {
                $submitted = isset($submittedrecords[$uid]) ? (int) $submittedrecords[$uid]->submitted_count : 0;
                $missedassigns = max(0, $totaloverdueassigns - $submitted);
                if ($missedassigns > 0) {
                    $signals[] = [
                        'rule' => 'missed_assignments',
                        'severity' => $missedassigns >= 2 ? 'critical' : 'warning',
                        'message' => get_string('signal_missed_activities', 'local_learningsuccess', $missedassigns),
                        'value' => $missedassigns,
                    ];
                    $riskscore += ($missedassigns * 15);
                }
            }

            // Determine Struggle Archetype Tag.
            if ($daysinactive >= $inactivitywarning) {
                $struggletag = [
                    'code' => 'disengaged',
                    'label' => get_string('struggle_disengaged', 'local_learningsuccess'),
                    'class' => 'badge-danger',
                    'icon' => 'fa-user-times',
                ];
            } else if ($maxattempt >= 2 && $percent !== null && $percent < 60) {
                $struggletag = [
                    'code' => 'repeated_attempts',
                    'label' => get_string('struggle_repeated_attempts', 'local_learningsuccess', $maxattempt),
                    'class' => 'badge-warning text-dark',
                    'icon' => 'fa-repeat',
                ];
            } else if ($missedassigns > 0) {
                $struggletag = [
                    'code' => 'overdue',
                    'label' => get_string('struggle_overdue', 'local_learningsuccess', $missedassigns),
                    'class' => 'badge-info',
                    'icon' => 'fa-clock-o',
                ];
            } else if ($rate < 50) {
                $struggletag = [
                    'code' => 'pacing',
                    'label' => get_string('struggle_pacing', 'local_learningsuccess'),
                    'class' => 'badge-secondary',
                    'icon' => 'fa-hourglass-half',
                ];
            } else {
                $struggletag = null;
            }

            $riskscore = min(100, $riskscore);

            // Determine status.
            if ($riskscore >= 70) {
                $status = analytics_adapter::STATUS_CRITICAL;
            } else if ($riskscore >= 45) {
                $status = analytics_adapter::STATUS_ATRISK;
            } else if ($riskscore >= 20) {
                $status = analytics_adapter::STATUS_MONITOR;
            } else {
                $status = analytics_adapter::STATUS_HEALTHY;
            }

            $results[] = [
                'userid' => $uid,
                'courseid' => $courseid,
                'fullname' => fullname($user),
                'status' => $status,
                'status_label' => get_string("status_{$status}", 'local_learningsuccess'),
                'risk_score' => $riskscore,
                'signals' => $signals,
                'signal_count' => count($signals),
                'struggle_tag' => $struggletag,
            ];
        }

        return $results;
    }
}

