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
            'has_critical_signals' => !empty(array_filter(
                $explanations,
                fn($s) => ($s['severity'] ?? '') === explanation::SEVERITY_CRITICAL
            )),
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
            $diff = $before['inactive_days'] - $after['inactive_days']; // Positive means fewer inactive days = improved!
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
            $diff = round($before['risk_score'] - $after['risk_score'], 1); // Positive means lower risk score = improved!
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

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $baseparams = array_merge(['courseid' => $courseid], $inparams);

        // Fetch shared batch metrics.
        $batch = \local_learningsuccess\local\helper\metrics_helper::get_course_metrics_batch($userids, $courseid);

        $course = $batch['course'];
        $coursestarted = $batch['coursestarted'];
        $enrolrecords = $batch['enrolrecords'];
        $lastaccessrecords = $batch['lastaccessrecords'];
        $totalmodules = $batch['totalmodules'];
        $completionrecords = $batch['completionrecords'];
        $graderecords = $batch['graderecords'];
        $quizattemptsrecords = $batch['quizattemptsrecords'];
        $totaloverdueassigns = $batch['totaloverdueassigns'];
        $submittedrecords = $batch['submittedrecords'];

        // Bulk query dismissed signals to honor teacher overrides in batch mode.
        $dismissedbyuser = [];
        if ($DB->get_manager()->table_exists('local_learningsuccess_sign')) {
            $signsql = "SELECT id, userid, signal_type, severity, dismissedat, timecreated
                          FROM {local_learningsuccess_sign}
                         WHERE courseid = :courseid
                               AND dismissed = 1
                               AND userid $insql";
            $dismissedrecords = $DB->get_records_sql($signsql, $baseparams);
            foreach ($dismissedrecords as $dr) {
                $dismissedbyuser[$dr->userid][$dr->signal_type] = $dr;
            }
        }

        $isdismissed = function (int $uid, string $type, string $curseverity) use ($dismissedbyuser, $now): bool {
            if (!isset($dismissedbyuser[$uid][$type])) {
                return false;
            }
            $rec = $dismissedbyuser[$uid][$type];
            $dismissedat = (int) ($rec->dismissedat ?? $rec->timecreated);
            // Expired after 14 days.
            if (($now - $dismissedat) > (14 * DAYSECS)) {
                return false;
            }
            // Escalation to critical.
            if ($curseverity === 'critical' && $rec->severity !== 'critical') {
                return false;
            }
            return true;
        };

        // Evaluate risks if not provided.
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
                $sev = ($daysinactive >= $inactivitycritical)
                    ? 'critical'
                    : (($daysinactive >= $inactivitywarning) ? 'warning' : null);
                if ($sev !== null && !$isdismissed($uid, 'inactivity', $sev)) {
                    $isnever = ($lastaccess <= 0);
                    $title = $isnever
                        ? get_string('signal_no_activity_title', 'local_learningsuccess')
                        : get_string('signal_inactivity_title', 'local_learningsuccess');
                    $msg = $isnever
                        ? get_string('signal_no_activity_desc', 'local_learningsuccess')
                        : (($sev === 'critical')
                            ? get_string('signal_inactivity_critical', 'local_learningsuccess', $daysinactive)
                            : get_string('signal_inactivity_warning', 'local_learningsuccess', $daysinactive));
                    $signals[] = [
                        'type' => 'inactivity',
                        'rule' => 'inactivity',
                        'severity' => $sev,
                        'title' => $title,
                        'message' => $msg,
                        'description' => $msg,
                        'value' => $daysinactive,
                        'evidence' => ['days' => $daysinactive, 'lastaccess' => $lastaccess],
                    ];
                }
            }

            // Completion rule (suppressed if course hasn't started or student enrolled recently).
            if ($totalmodules > 0 && $coursestarted && !$isrecentlyenrolled) {
                $completed = isset($completionrecords[$uid])
                    ? (int) ($completionrecords[$uid]->completed_count ?? $completionrecords[$uid]->completedcount ?? 0)
                    : 0;
                $rate = (int) round(($completed / $totalmodules) * 100);
                $sev = ($rate < 30) ? 'critical' : (($rate < 60) ? 'warning' : null);
                if ($sev !== null && !$isdismissed($uid, 'completion', $sev)) {
                    $msg = get_string('signal_completion_low', 'local_learningsuccess', $rate);
                    $signals[] = [
                        'type' => 'completion',
                        'rule' => 'completion',
                        'severity' => $sev,
                        'message' => $msg,
                        'description' => $msg,
                        'value' => $rate,
                    ];
                }
            }

            // Grade performance rule.
            if (isset($graderecords[$uid]) && $graderecords[$uid]->grademax > 0 && $graderecords[$uid]->finalgrade !== null) {
                $percent = (int) round(($graderecords[$uid]->finalgrade / $graderecords[$uid]->grademax) * 100);
                $sev = ($percent < 40) ? 'critical' : (($percent < 50) ? 'warning' : null);
                if ($sev !== null && !$isdismissed($uid, 'grade_decline', $sev) && !$isdismissed($uid, 'grade_performance', $sev)) {
                    $msg = get_string('signal_quiz_low', 'local_learningsuccess', $percent);
                    $signals[] = [
                        'type' => 'grade_decline',
                        'rule' => 'grade_decline',
                        'severity' => $sev,
                        'message' => $msg,
                        'description' => $msg,
                        'value' => $percent,
                    ];
                }
            }

            // Quiz Struggle (repeated retries).
            $maxattempt = isset($quizattemptsrecords[$uid]) ? (int) $quizattemptsrecords[$uid]->max_attempt : 0;
            if ($maxattempt >= 2 && !$isdismissed($uid, 'quiz_retries', 'warning')) {
                $msg = get_string('signal_quiz_retries', 'local_learningsuccess', $maxattempt);
                $signals[] = [
                    'type' => 'quiz_retries',
                    'rule' => 'quiz_retries',
                    'severity' => 'warning',
                    'message' => $msg,
                    'description' => $msg,
                    'value' => $maxattempt,
                ];
            }

            // Overdue assignments.
            $missedassigns = 0;
            if ($totaloverdueassigns > 0) {
                $submitted = isset($submittedrecords[$uid]) ? (int) $submittedrecords[$uid]->submitted_count : 0;
                $missedassigns = max(0, $totaloverdueassigns - $submitted);
                if ($missedassigns > 0) {
                    $sev = ($missedassigns >= 2) ? 'critical' : 'warning';
                    if (!$isdismissed($uid, 'overdue', $sev) && !$isdismissed($uid, 'missed_assignments', $sev)) {
                        $msg = get_string('signal_missed_activities', 'local_learningsuccess', $missedassigns);
                        $signals[] = [
                            'type' => 'overdue',
                            'rule' => 'overdue',
                            'severity' => $sev,
                            'message' => $msg,
                            'description' => $msg,
                            'value' => $missedassigns,
                        ];
                    }
                }
            }

            // Determine Struggle Archetype Tag.
            if ($daysinactive >= $inactivitywarning) {
                $struggletag = [
                    'code' => 'disengaged',
                    'label' => get_string('struggle_disengaged', 'local_learningsuccess'),
                    'class' => 'badge-urgent',
                    'icon' => 'fa-user-times',
                ];
            } else if ($maxattempt >= 2 && $percent !== null && $percent < 60) {
                $struggletag = [
                    'code' => 'repeated_attempts',
                    'label' => get_string('struggle_repeated_attempts', 'local_learningsuccess', $maxattempt),
                    'class' => 'badge-recommend',
                    'icon' => 'fa-repeat',
                ];
            } else if ($missedassigns > 0) {
                $struggletag = [
                    'code' => 'overdue',
                    'label' => get_string('struggle_overdue', 'local_learningsuccess', $missedassigns),
                    'class' => 'badge-monitor',
                    'icon' => 'fa-clock-o',
                ];
            } else if ($rate < 50) {
                $struggletag = [
                    'code' => 'pacing',
                    'label' => get_string('struggle_pacing', 'local_learningsuccess'),
                    'class' => 'badge-nodata',
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
