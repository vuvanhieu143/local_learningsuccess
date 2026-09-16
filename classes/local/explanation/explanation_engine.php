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

namespace local_learningsuccess\local\explanation;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\risk\risk_provider;
use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\local\risk\moodle_analytics_provider;
use local_learningsuccess\local\signal\signal_collector;

/**
 * Domain engine that synthesizes student signals and risk evaluations into explainable evidence.
 *
 * Keeps observable evidence strictly separate from actionable recommendations.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class explanation_engine {

    /** @var risk_provider */
    protected risk_provider $riskprovider;

    /** @var signal_collector */
    protected signal_collector $signalcollector;

    /**
     * Constructor.
     *
     * @param risk_provider|null $riskprovider
     * @param signal_collector|null $signalcollector
     */
    public function __construct(
        ?risk_provider $riskprovider = null,
        ?signal_collector $signalcollector = null
    ) {
        $this->riskprovider = $riskprovider ?? new moodle_analytics_provider();
        $this->signalcollector = $signalcollector ?? new signal_collector();
    }

    /**
     * Build and normalize explanation items from a risk result and raw signal list.
     *
     * @param risk_result $risk
     * @param explanation[]|array $signals
     * @return array Array of normalized explanation arrays.
     */
    public function build(risk_result $risk, array $signals): array {
        $normalized = [];

        foreach ($signals as $item) {
            if ($item instanceof explanation) {
                $normalized[] = $item->to_array();
            } else if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        // Deduplicate by type.
        $deduped = [];
        foreach ($normalized as $exp) {
            $type = $exp['type'] ?? 'unknown';
            if (!isset($deduped[$type])) {
                $deduped[$type] = $exp;
            }
        }

        // Sort by severity: critical (3) > warning/high (2) > info/low (1).
        $severityweight = [
            explanation::SEVERITY_CRITICAL => 3,
            'high' => 2,
            explanation::SEVERITY_WARNING => 2,
            explanation::SEVERITY_INFO => 1,
            'low' => 1,
        ];

        uasort($deduped, function ($a, $b) use ($severityweight) {
            $wa = $severityweight[$a['severity'] ?? ''] ?? 0;
            $wb = $severityweight[$b['severity'] ?? ''] ?? 0;
            return $wb <=> $wa;
        });

        return array_values($deduped);
    }

    /**
     * Explain why a student requires attention in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function explain_student(int $userid, int $courseid): array {
        $risk = $this->riskprovider->get_risk($userid, $courseid);
        $signals = $this->signalcollector->collect($userid, $courseid);
        $explanations = $this->build($risk, $signals);

        $statuskey = $risk->get_level();
        $statuslabel = get_string('status_' . $statuskey, 'local_learningsuccess');
        $whynow = $this->generate_why_now($risk, $explanations, null);

        return [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => $statuskey,
            'risk_score' => (int) round($risk->get_score()),
            'status_label' => $statuslabel,
            'source' => $risk->get_source(),
            'model' => $risk->get_model(),
            'signals' => $explanations,
            'signal_count' => count($explanations),
            'has_critical_signals' => !empty(array_filter($explanations, fn($s) => ($s['severity'] ?? '') === explanation::SEVERITY_CRITICAL)),
            'why_now' => $whynow,
        ];
    }

    /**
     * Synthesize structured "Why now?" bullet points for teacher quick-reading.
     *
     * @param risk_result $risk
     * @param array $explanations
     * @param \stdClass|null $activeintervention
     * @return string[]
     */
    public function generate_why_now(
        risk_result $risk,
        array $explanations,
        ?\stdClass $activeintervention = null
    ): array {
        $reasons = [];

        // 1. Observable signals.
        foreach ($explanations as $exp) {
            $desc = is_array($exp) ? ($exp['description'] ?? $exp['title'] ?? '') : ($exp->get_description() ?? '');
            if (!empty($desc)) {
                $reasons[] = $desc;
            }
        }

        // 2. Active intervention context.
        if ($activeintervention !== null) {
            $status = strtolower($activeintervention->status);
            $followupat = isset($activeintervention->followupat) ? (int) $activeintervention->followupat : 0;
            if ($followupat > 0 && $followupat <= time()) {
                $days = (int) floor((time() - $followupat) / DAYSECS);
                $reasons[] = $days > 0
                    ? "Intervention follow-up is {$days} days overdue."
                    : "Intervention follow-up is due today.";
            } else {
                $reasons[] = "Active intervention in progress (" . ucfirst($status) . ").";
            }
        } else if ($risk->get_level() === risk_result::LEVEL_CRITICAL || $risk->get_level() === risk_result::LEVEL_ATRISK) {
            $reasons[] = "No active teacher intervention in place.";
        }

        return $reasons;
    }

    /**
     * Compare before and after metric snapshots to answer "What changed?".
     *
     * Uses neutral evidence-based language ("Indicators improved after intervention").
     *
     * @param array|null $before
     * @param array|null $after
     * @return array
     */
    public function compare_snapshots(?array $before, ?array $after): array {
        if (empty($before) || empty($after)) {
            return [
                'has_changes' => false,
                'metrics' => [],
            ];
        }

        $metrics = [];

        // 1. Completion comparison.
        if (isset($before['completion']) && isset($after['completion'])) {
            $diff = round($after['completion'] - $before['completion'], 1);
            $direction = $diff > 0 ? 'improved' : ($diff < 0 ? 'declined' : 'unchanged');
            $metrics[] = [
                'metric' => 'completion',
                'label' => 'Module Completion',
                'before' => round($before['completion'], 1) . '%',
                'after' => round($after['completion'], 1) . '%',
                'difference' => ($diff > 0 ? '+' : '') . $diff . '%',
                'direction' => $direction,
                'direction_label' => ucfirst($direction),
                'icon' => $direction === 'improved' ? 'fa-arrow-up' : ($direction === 'declined' ? 'fa-arrow-down' : 'fa-minus'),
                'class' => $direction === 'improved' ? 'text-success' : ($direction === 'declined' ? 'text-danger' : 'text-muted'),
            ];
        }

        // 2. Grade comparison.
        if (isset($before['grade']) && isset($after['grade'])) {
            $diff = round($after['grade'] - $before['grade'], 1);
            $direction = $diff > 0 ? 'improved' : ($diff < 0 ? 'declined' : 'unchanged');
            $metrics[] = [
                'metric' => 'grade',
                'label' => 'Course Grade',
                'before' => round($before['grade'], 1) . '%',
                'after' => round($after['grade'], 1) . '%',
                'difference' => ($diff > 0 ? '+' : '') . $diff . '%',
                'direction' => $direction,
                'direction_label' => ucfirst($direction),
                'icon' => $direction === 'improved' ? 'fa-arrow-up' : ($direction === 'declined' ? 'fa-arrow-down' : 'fa-minus'),
                'class' => $direction === 'improved' ? 'text-success' : ($direction === 'declined' ? 'text-danger' : 'text-muted'),
            ];
        }

        // 3. Activity comparison (inactive days - lower is better).
        if (isset($before['inactive_days']) && isset($after['inactive_days'])) {
            $diff = $before['inactive_days'] - $after['inactive_days']; // positive means fewer inactive days = improved!
            $direction = $diff > 0 ? 'improved' : ($diff < 0 ? 'declined' : 'unchanged');
            $metrics[] = [
                'metric' => 'activity',
                'label' => 'Days Inactive',
                'before' => $before['inactive_days'] . ' days',
                'after' => $after['inactive_days'] . ' days',
                'difference' => ($diff > 0 ? '-' : '+') . abs($diff) . ' days',
                'direction' => $direction,
                'direction_label' => ucfirst($direction),
                'icon' => $direction === 'improved' ? 'fa-arrow-up' : ($direction === 'declined' ? 'fa-arrow-down' : 'fa-minus'),
                'class' => $direction === 'improved' ? 'text-success' : ($direction === 'declined' ? 'text-danger' : 'text-muted'),
            ];
        }

        // 4. Risk score comparison (lower is better).
        if (isset($before['risk_score']) && isset($after['risk_score'])) {
            $diff = round($before['risk_score'] - $after['risk_score'], 1); // positive means lower risk score = improved!
            $direction = $diff > 0 ? 'improved' : ($diff < 0 ? 'declined' : 'unchanged');
            $metrics[] = [
                'metric' => 'risk_score',
                'label' => 'Risk Score',
                'before' => (string) round($before['risk_score'], 1),
                'after' => (string) round($after['risk_score'], 1),
                'difference' => ($diff > 0 ? '-' : '+') . abs($diff),
                'direction' => $direction,
                'direction_label' => ucfirst($direction),
                'icon' => $direction === 'improved' ? 'fa-arrow-down' : ($direction === 'declined' ? 'fa-arrow-up' : 'fa-minus'),
                'class' => $direction === 'improved' ? 'text-success' : ($direction === 'declined' ? 'text-danger' : 'text-muted'),
            ];
        }

        return [
            'has_changes' => !empty($metrics),
            'metrics' => $metrics,
        ];
    }

    /**
     * Batch process and explain multiple students using high-performance bulk SQL queries.
     * Reduces O(N) database queries down to O(1) bulk lookups.
     *
     * @param array $enrolledusers Array of enrolled user objects/records
     * @param int $courseid Target course ID
     * @param array<int, risk_result> $risks Optional pre-calculated risks keyed by userid
     * @return array Array of explained student profiles
     */
    public function batch_explain(array $enrolledusers, int $courseid, array $risks = []): array {
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

        // Course start date and lifecycle.
        $course = $DB->get_record('course', ['id' => $courseid], 'id, startdate', MUST_EXIST);
        $coursestarted = ($course->startdate <= 0 || $course->startdate <= $now);

        // Bulk query user enrolments for grace period calculations.
        $enrolsql = "SELECT ue.userid, MIN(COALESCE(NULLIF(ue.timestart, 0), ue.timecreated)) AS enroltime
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE e.courseid = :courseid AND ue.userid $insql
                   GROUP BY ue.userid";
        $enrolrecords = $DB->get_records_sql($enrolsql, $baseparams);

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

        // 6. Evaluate risks if not provided.
        if (empty($risks)) {
            $risks = $this->riskprovider->get_risks($userids, $courseid);
        }

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
            $enroltime = isset($enrolrecords[$uid]) ? (int) $enrolrecords[$uid]->enroltime : $now;
            $isrecentlyenrolled = ($now - $enroltime) < (5 * DAYSECS);
            $lastaccess = isset($lastaccessrecords[$uid]) ? (int) $lastaccessrecords[$uid]->timeaccess : 0;

            if ($lastaccess > 0) {
                $daysinactive = (int) floor(($now - $lastaccess) / DAYSECS);
            } else if (!$coursestarted) {
                $daysinactive = 0;
            } else if ($isrecentlyenrolled) {
                $baseline = max($enroltime, (int) $course->startdate);
                $daysinactive = (int) floor(max(0, $now - $baseline) / DAYSECS);
            } else {
                $baseline = max($enroltime, (int) $course->startdate);
                $daysinactive = (int) floor(max(0, $now - $baseline) / DAYSECS);
                if ($daysinactive < 14) {
                    $daysinactive = max(14, $daysinactive);
                }
            }

            if ($coursestarted && (!$isrecentlyenrolled || $daysinactive >= 7)) {
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

            // Completion rule (suppressed if course hasn't started or student enrolled recently).
            if ($totalmodules > 0 && $coursestarted && !$isrecentlyenrolled) {
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
                    'class' => 'text-bg-danger',
                    'icon' => 'fa-user-times',
                ];
            } else if ($maxattempt >= 2 && $percent !== null && $percent < 60) {
                $struggletag = [
                    'code' => 'repeated_attempts',
                    'label' => get_string('struggle_repeated_attempts', 'local_learningsuccess', $maxattempt),
                    'class' => 'text-bg-warning',
                    'icon' => 'fa-repeat',
                ];
            } else if ($missedassigns > 0) {
                $struggletag = [
                    'code' => 'overdue',
                    'label' => get_string('struggle_overdue', 'local_learningsuccess', $missedassigns),
                    'class' => 'text-bg-info',
                    'icon' => 'fa-clock-o',
                ];
            } else if ($rate < 50) {
                $struggletag = [
                    'code' => 'pacing',
                    'label' => get_string('struggle_pacing', 'local_learningsuccess'),
                    'class' => 'text-bg-secondary',
                    'icon' => 'fa-hourglass-half',
                ];
            } else {
                $struggletag = null;
            }

            // Ensure all name fields exist to prevent debugging() warnings in fullname().
            foreach (\core_user\fields::get_name_fields() as $nf) {
                if (!isset($user->$nf)) {
                    $user->$nf = '';
                }
            }

            $results[] = [
                'userid' => $uid,
                'courseid' => $courseid,
                'fullname' => fullname($user),
                'status' => $status,
                'status_label' => get_string("status_{$status}", 'local_learningsuccess'),
                'risk_score' => $riskscore,
                'source' => $risk->get_source(),
                'model' => $risk->get_model(),
                'signals' => $signals,
                'signal_count' => count($signals),
                'struggle_tag' => $struggletag,
            ];
        }

        return $results;
    }
}
