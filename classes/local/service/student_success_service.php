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
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_success_service {
    /** @var risk_provider Risk provider instance. */
    protected risk_provider $riskprovider;

    /** @var explanation_engine Explanation engine instance. */
    protected explanation_engine $explanationengine;

    /** @var recommendation_engine Recommendation engine instance. */
    protected recommendation_engine $recommendationengine;

    /** @var intervention_manager Intervention manager instance. */
    protected intervention_manager $interventionmanager;

    /** @var actionability_engine Actionability engine instance. */
    protected actionability_engine $actionabilityengine;

    /**
     * Constructor.
     *
     * @param risk_provider|null $riskprovider
     * @param explanation_engine|null $explanationengine
     * @param recommendation_engine|null $recommendationengine
     * @param intervention_manager|null $interventionmanager
     * @param actionability_engine|null $actionabilityengine
     */
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
     * @param int|bool $groupid Optional group ID to filter by, or bool to skip cache
     * @param bool $skipcache
     * @return array
     */
    public function get_course_summary(int $courseid, int|bool $groupid = 0, bool $skipcache = false): array {
        global $DB;

        if (is_bool($groupid)) {
            $skipcache = $groupid;
            $groupid = 0;
        }

        $cache = cache::make('local_learningsuccess', 'course_summary');
        $cachekey = $groupid > 0 ? "{$courseid}_{$groupid}" : $courseid;
        if (!$skipcache) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch active enrolled students in course (or group), excluding suspended enrolments.
        $context = \context_course::instance($courseid);
        $userfieldsapi = \core_user\fields::for_name();
        $userfields = 'u.id, ' . $userfieldsapi->get_sql('u', false, '', '', false)->selects;
        $enrolledusers = get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            $groupid,
            $userfields,
            null,
            0,
            0,
            true
        );
        if (empty($enrolledusers)) {
            // Fallback to active enrolled learners if completion capability is not explicitly assigned.
            $enrolledusers = get_enrolled_users($context, '', $groupid, $userfields, null, 0, 0, true);
        }

        $total = count($enrolledusers);
        $counts = [
            risk_result::LEVEL_HEALTHY => 0,
            risk_result::LEVEL_MONITOR => 0,
            risk_result::LEVEL_ATRISK => 0,
            risk_result::LEVEL_CRITICAL => 0,
            risk_result::LEVEL_NO_DATA => 0,
        ];

        $studentprofiles = $this->batch_explain_students($enrolledusers, $courseid);
        foreach ($studentprofiles as $profile) {
            $st = $profile['status'] ?? risk_result::LEVEL_HEALTHY;
            if (!isset($counts[$st])) {
                $counts[$st] = 0;
            }
            $counts[$st]++;
        }

        // Active and resolved interventions.
        $activeinterventions = $DB->count_records_select(
            'local_learningsuccess_int',
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
            'local_learningsuccess_int',
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
            'nodata_count' => $counts[risk_result::LEVEL_NO_DATA],
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

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $explained = $this->explanationengine->explain_student($userid, $courseid);
        $recommendations = $this->recommendationengine->recommend($explained['signals']);
        $interventions = $this->interventionmanager->get_for_student($userid, $courseid);
        $activeintervention = $this->interventionmanager->get_active_for_student($userid, $courseid);

        // Evaluate actionability and work-queue prioritization.
        $riskobj = new risk_result(
            score: (float) $explained['risk_score'],
            level: $explained['status'],
            source: $explained['source'] ?? 'course_activity_signals',
            model: $explained['model'] ?? null
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
            ],
            risk: [
                'score' => $explained['risk_score'],
                'level' => $explained['status'],
                'source' => $explained['source'] ?? 'course_activity_signals',
                'model' => $explained['model'] ?? null,
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
     * Retrieve recent interventions where student indicators improved.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public function get_recent_improvements(int $courseid, int $limit = 5): array {
        global $DB;

        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $sql = "SELECT i.id, i.userid, i.type, i.actual_action, i.completed_at, i.timemodified $namefields
                  FROM {local_learningsuccess_int} i
                  JOIN {user} u ON u.id = i.userid
                 WHERE i.courseid = :courseid
                       AND i.status = :completed
                       AND i.outcome = :improved
              ORDER BY COALESCE(i.completed_at, i.timemodified) DESC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'completed' => intervention_manager::STATUS_COMPLETED,
            'improved' => outcome::IMPROVED,
        ], 0, $limit);

        $improvements = [];
        foreach ($records as $r) {
            $completedtime = $r->completed_at ? (int) $r->completed_at : (int) $r->timemodified;
            $typekey = 'type_' . strtolower($r->type);
            $improvements[] = [
                'id' => (int) $r->id,
                'userid' => (int) $r->userid,
                'fullname' => fullname($r),
                'type' => $r->type,
                'type_label' => get_string($typekey, 'local_learningsuccess'),
                'action_note' => $r->actual_action,
                'completed_date' => userdate($completedtime, get_string('strftimedatemonthabbr', 'langconfig')),
                'evidence_summary' => get_string('indicators_improved_desc', 'local_learningsuccess'),
            ];
        }

        return $improvements;
    }

    /**
     * Backward-compatible alias for get_recent_improvements.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public function get_recent_success_stories(int $courseid, int $limit = 5): array {
        return $this->get_recent_improvements($courseid, $limit);
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
     * Delegates domain metric extraction and explanation calculations to explanation_engine::batch_explain().
     *
     * @param array $enrolledusers Array of enrolled user records
     * @param int $courseid Target course ID
     * @return array Array of explained student profiles
     */
    public function batch_explain_students(array $enrolledusers, int $courseid): array {
        if (empty($enrolledusers)) {
            return [];
        }

        $userids = array_map(fn($u) => (int) $u->id, array_values($enrolledusers));
        $risks = $this->riskprovider->get_risks($userids, $courseid);

        return $this->explanationengine->batch_explain($enrolledusers, $courseid, $risks);
    }

    /**
     * Retrieve students recently handled via interventions (completed or contacted within the last 7 days).
     *
     * Provides transparency on why students recently transitioned out of the active priorities queue.
     *
     * @param int $courseid Target course ID
     * @param int $limit Maximum records to return
     * @return array Array of recently handled student records
     */
    public function get_recently_handled_students(int $courseid, int $limit = 5): array {
        global $DB;

        $userfieldsapi = \core_user\fields::for_name();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT i.id, i.userid, i.type, i.status, i.outcome, i.timemodified, i.completed_at, i.actual_action,
                       $userfields
                  FROM {local_learningsuccess_int} i
                  JOIN {user} u ON u.id = i.userid
                 WHERE i.courseid = :courseid
                       AND i.status IN (:completed, :contacted, :dismissed)
                       AND i.timemodified >= :since
              ORDER BY i.timemodified DESC";

        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'completed' => intervention_status::COMPLETED,
            'contacted' => intervention_status::CONTACTED,
            'dismissed' => intervention_status::DISMISSED,
            'since' => time() - (7 * DAYSECS),
        ], 0, $limit);

        $results = [];
        foreach ($records as $r) {
            $results[] = [
                'id' => (int) $r->id,
                'userid' => (int) $r->userid,
                'fullname' => fullname($r),
                'type' => $r->type,
                'status' => $r->status,
                'status_label' => ucfirst($r->status),
                'action_note' => $r->actual_action ?? '',
                'handled_date' => userdate($r->timemodified, get_string('strftimedateshort', 'langconfig')),
            ];
        }

        return $results;
    }
}
