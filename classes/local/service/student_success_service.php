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
use local_learningsuccess\local\actionability\actionability_engine;
use local_learningsuccess\local\actionability\actionability_result;
use local_learningsuccess\local\explanation\explanation_engine;
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\intervention\intervention_status;
use local_learningsuccess\local\outcome\outcome;
use local_learningsuccess\local\recommendation\recommendation_engine;
use local_learningsuccess\local\risk\moodle_analytics_provider;
use local_learningsuccess\local\risk\risk_provider;
use local_learningsuccess\local\risk\risk_result;

/**
 * Application service assembling high-performance views for teachers and course dashboards.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_success_service {

    protected risk_provider $riskprovider;
    protected explanation_engine $explanationengine;
    protected recommendation_engine $recommendationengine;
    protected intervention_manager $interventionmanager;
    protected actionability_engine $actionabilityengine;

    public function __construct(
        ?risk_provider $riskprovider = null,
        ?explanation_engine $explanationengine = null,
        ?recommendation_engine $recommendationengine = null,
        ?intervention_manager $interventionmanager = null,
        ?actionability_engine $actionabilityengine = null
    ) {
        $this->riskprovider = $riskprovider ?? new moodle_analytics_provider();
        $this->explanationengine = $explanationengine ?? new explanation_engine($this->riskprovider);
        $this->recommendationengine = $recommendationengine ?? new recommendation_engine();
        $this->interventionmanager = $interventionmanager ?? new intervention_manager();
        $this->actionabilityengine = $actionabilityengine ?? new actionability_engine(
            $this->recommendationengine,
            $this->interventionmanager
        );
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
            risk_result::LEVEL_HEALTHY => 0,
            risk_result::LEVEL_MONITOR => 0,
            risk_result::LEVEL_ATRISK => 0,
            risk_result::LEVEL_CRITICAL => 0,
        ];

        $studentprofiles = $this->batch_explain_students($enrolledusers, $courseid);
        foreach ($studentprofiles as $profile) {
            $counts[$profile['status']]++;
        }

        // Active and resolved interventions.
        $activeinterventions = $DB->count_records_select(
            'local_ls_intervention',
            'courseid = :courseid AND status IN (:open, :contacted, :waiting, :followup, :inprogress)',
            [
                'courseid' => $courseid,
                'open' => intervention_status::OPEN,
                'contacted' => intervention_status::CONTACTED,
                'waiting' => intervention_status::WAITING,
                'followup' => intervention_status::FOLLOW_UP,
                'inprogress' => intervention_status::IN_PROGRESS,
            ]
        );

        $resolvedinterventions = $DB->count_records(
            'local_ls_intervention',
            [
                'courseid' => $courseid,
                'status' => intervention_manager::STATUS_COMPLETED,
                'outcome' => outcome::IMPROVED,
            ]
        );

        $result = [
            'courseid' => $courseid,
            'total_students' => $total,
            'healthy_count' => $counts[risk_result::LEVEL_HEALTHY],
            'monitor_count' => $counts[risk_result::LEVEL_MONITOR],
            'atrisk_count' => $counts[risk_result::LEVEL_ATRISK],
            'critical_count' => $counts[risk_result::LEVEL_CRITICAL],
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
        $activeintervention = $this->interventionmanager->get_active_for_student($userid, $courseid);

        // Evaluate actionability and work-queue prioritization.
        $riskobj = new risk_result(
            score: (float) $explained['risk_score'],
            level: $explained['status'],
            source: $explained['source'] ?? 'course_activity_signals'
        );
        $actionability = $this->actionabilityengine->evaluate(
            userid: $userid,
            courseid: $courseid,
            risk: $riskobj,
            signals: $explained['signals'],
            activeintervention: $activeintervention
        );

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
            notes: $allnotes,
            recentoutcomes: [],
            actionability: $actionability->to_array()
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

        if (empty($students)) {
            return [];
        }

        $activeinterventions = $this->interventionmanager->get_active_for_course_by_user($courseid);
        $actionabilities = $this->actionabilityengine->batch_evaluate($students, $courseid, $activeinterventions);

        $workqueue = [];
        foreach ($students as $s) {
            $uid = (int) $s['userid'];
            $act = $actionabilities[$uid] ?? null;
            if ($act === null) {
                continue;
            }

            // Exclude healthy students needing no action.
            if ($act->get_level() === actionability_result::LEVEL_NO_ACTION) {
                continue;
            }

            $s['actionability'] = $act->to_array();
            $s['actionability_level'] = $act->get_level();
            $s['actionability_level_label'] = $act->get_level_label();
            $s['priority_score'] = $act->get_priority_score();
            $s['summary_reason'] = $act->get_summary_reason();
            $s['why_now_reasons'] = $act->get_reasons();
            $s['primary_action'] = $act->get_primary_action();
            $s['alternative_actions'] = $act->get_alternatives();
            $s['has_active_intervention'] = $act->get_active_intervention() !== null;

            $workqueue[] = $s;
        }

        // Sort descending by priority score (Urgent -> Follow-up -> Recommend -> Monitor).
        usort($workqueue, function ($a, $b) {
            if ($a['priority_score'] === $b['priority_score']) {
                return $b['risk_score'] <=> $a['risk_score'];
            }
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return array_values(array_slice($workqueue, 0, $limit));
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

        // 6. Bulk evaluate risks using unified risk_provider.
        $userids = array_map(fn($u) => (int) $u->id, $enrolledusers);
        $risks = $this->riskprovider->get_risks($userids, $courseid);

        $results = [];

        foreach ($enrolledusers as $user) {
            $uid = (int) $user->id;
            $risk = $risks[$uid] ?? new risk_result(0.0, risk_result::LEVEL_HEALTHY, 'course_activity_signals');
            $riskscore = (int) round($risk->get_score());
            $status = $risk->get_level();

            $signals = [];
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
            } else {
                $daysinactive = (int) floor(($now - $lastaccess) / DAYSECS);
                if ($daysinactive >= $inactivitycritical) {
                    $signals[] = [
                        'rule' => 'inactivity',
                        'severity' => 'critical',
                        'message' => get_string('signal_inactivity_critical', 'local_learningsuccess', $daysinactive),
                        'value' => $daysinactive,
                    ];
                } else if ($daysinactive >= $inactivitywarning) {
                    $signals[] = [
                        'rule' => 'inactivity',
                        'severity' => 'warning',
                        'message' => get_string('signal_inactivity_warning', 'local_learningsuccess', $daysinactive),
                        'value' => $daysinactive,
                    ];
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
                } else if ($rate < 60) {
                    $signals[] = [
                        'rule' => 'completion',
                        'severity' => 'warning',
                        'message' => get_string('signal_completion_low', 'local_learningsuccess', $rate),
                        'value' => $rate,
                    ];
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
                } else if ($percent < 50) {
                    $signals[] = [
                        'rule' => 'grade_performance',
                        'severity' => 'warning',
                        'message' => get_string('signal_quiz_low', 'local_learningsuccess', $percent),
                        'value' => $percent,
                    ];
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

