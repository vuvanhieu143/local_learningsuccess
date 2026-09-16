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

namespace local_learningsuccess\local\actionability;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\local\recommendation\recommendation_engine;
use local_learningsuccess\local\intervention\intervention_manager;
use local_learningsuccess\local\intervention\intervention_status;

/**
 * Domain engine evaluating student actionability and work-queue prioritization.
 *
 * Distinguishes pure mathematical risk from teacher action urgency.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actionability_engine {

    /** @var recommendation_engine */
    private recommendation_engine $recommendationengine;

    /** @var intervention_manager */
    private intervention_manager $interventionmanager;

    /**
     * Constructor.
     *
     * @param recommendation_engine|null $recommendationengine
     * @param intervention_manager|null $interventionmanager
     */
    public function __construct(
        ?recommendation_engine $recommendationengine = null,
        ?intervention_manager $interventionmanager = null
    ) {
        $this->recommendationengine = $recommendationengine ?? new recommendation_engine();
        $this->interventionmanager = $interventionmanager ?? new intervention_manager();
    }

    /**
     * Evaluate actionability for a single student.
     *
     * @param int $userid
     * @param int $courseid
     * @param risk_result $risk
     * @param array $signals
     * @param \stdClass|null $activeintervention Optional pre-fetched active intervention record
     * @param int|null $now Reference timestamp (defaults to current time)
     * @return actionability_result
     */
    public function evaluate(
        int $userid,
        int $courseid,
        risk_result $risk,
        array $signals,
        ?\stdClass $activeintervention = null,
        ?int $now = null
    ): actionability_result {
        $now = $now ?? time();

        if ($activeintervention === null) {
            $activeintervention = $this->interventionmanager->get_active_for_student($userid, $courseid);
        }

        $reasons = [];
        $summaryreason = '';
        $level = actionability_result::LEVEL_NO_ACTION;
        $priorityscore = 0.0;
        $primaryaction = null;
        $alternatives = [];

        // 1. Check if active intervention exists and requires follow-up.
        $hasactive = ($activeintervention !== null && intervention_status::is_active($activeintervention->status));

        if ($hasactive) {
            $invstatus = strtolower($activeintervention->status);
            $followupat = isset($activeintervention->followupat) ? (int) $activeintervention->followupat : 0;
            $isfollowupdue = ($invstatus === intervention_status::FOLLOW_UP)
                || ($followupat > 0 && $followupat <= $now);

            if ($isfollowupdue) {
                // Follow-up is due. High priority in the work queue.
                $level = actionability_result::LEVEL_FOLLOW_UP;
                $overduedays = $followupat > 0 ? (int) floor(max(0, $now - $followupat) / DAYSECS) : 0;
                $priorityscore = 2500.0 + ($overduedays * 20.0) + ($risk->get_score() * 2.0);

                $dueinfo = $overduedays > 0
                    ? "{$overduedays} days overdue"
                    : "scheduled for review";
                $reasons[] = "Intervention follow-up is due ({$dueinfo}).";
                $summaryreason = "Follow-up review is due for student. Current status: " . ucfirst($invstatus) . ".";

                $primaryaction = [
                    'type' => 'review_followup',
                    'title' => get_string('action_review_followup', 'local_learningsuccess'),
                    'action' => 'Review recent student learning indicators, compare with initial snapshot, and record outcome.',
                    'intervention_id' => (int) $activeintervention->id,
                    'status' => $invstatus,
                    'urgency' => 'high',
                ];

                // Alternative escalation recommendations if student continues struggling.
                $recpartition = $this->recommendationengine->get_primary_and_alternatives($signals, $activeintervention);
                $alternatives = $recpartition['alternatives'];
                if ($recpartition['primary'] !== null) {
                    array_unshift($alternatives, $recpartition['primary']);
                }
            } else if (in_array($invstatus, [intervention_status::WAITING, intervention_status::CONTACTED], true)) {
                // Teacher has already contacted student; awaiting response or observation period.
                $level = actionability_result::LEVEL_MONITOR;
                $priorityscore = 100.0 + ($risk->get_score() * 0.5);

                $daysuntilfollowup = (int) ceil(max(0, $followupat - $now) / DAYSECS);
                $reasons[] = "Intervention in progress (" . ucfirst($invstatus) . "). Awaiting response or indicator change.";
                $reasons[] = "Next scheduled follow-up in {$daysuntilfollowup} day(s).";
                $summaryreason = "Already contacted. In observation window until follow-up date.";

                $primaryaction = null;
                $alternatives = [];
            } else {
                // Open intervention draft that has not been dispatched yet.
                $level = actionability_result::LEVEL_URGENT;
                $priorityscore = 2800.0 + ($risk->get_score() * 3.0);

                $reasons[] = "Pending open intervention draft requiring teacher outreach.";
                $summaryreason = "Intervention created but not yet sent or resolved.";

                $recpartition = $this->recommendationengine->get_primary_and_alternatives($signals, $activeintervention);
                $primaryaction = $recpartition['primary'];
                $alternatives = $recpartition['alternatives'];
            }
        } else {
            // 2. No active intervention: evaluate risk and observable signals.
            $risklevel = $risk->get_level();
            $riskscore = $risk->get_score();
            $signalcount = count($signals);

            // Extract observable signal messages for "Why now?".
            foreach ($signals as $sig) {
                if (!empty($sig['message'])) {
                    $reasons[] = $sig['message'];
                }
            }

            if ($risklevel === risk_result::LEVEL_CRITICAL) {
                $level = actionability_result::LEVEL_URGENT;
                $priorityscore = 3000.0 + ($riskscore * 4.0) + ($signalcount * 20.0);
                $reasons[] = "Critical learning risk detected with no active intervention in place.";
                $summaryreason = !empty($signals[0]['message'])
                    ? $signals[0]['message'] . " (Critical Risk)"
                    : "Student exhibits critical risk indicators and requires outreach.";
            } else if ($risklevel === risk_result::LEVEL_ATRISK) {
                $hascriticalsignal = false;
                foreach ($signals as $sig) {
                    if (($sig['severity'] ?? '') === 'critical') {
                        $hascriticalsignal = true;
                        break;
                    }
                }

                if ($hascriticalsignal || $signalcount >= 2) {
                    $level = actionability_result::LEVEL_URGENT;
                    $priorityscore = 2600.0 + ($riskscore * 3.0) + ($signalcount * 15.0);
                    $summaryreason = !empty($signals[0]['message'])
                        ? $signals[0]['message'] . " (Multiple risk signals)"
                        : "Multiple risk signals detected without intervention.";
                } else {
                    $level = actionability_result::LEVEL_RECOMMEND;
                    $priorityscore = 1500.0 + ($riskscore * 2.0);
                    $summaryreason = !empty($signals[0]['message'])
                        ? $signals[0]['message']
                        : "Student is at risk; teacher intervention recommended.";
                }
            } else if ($risklevel === risk_result::LEVEL_MONITOR) {
                if ($signalcount > 0) {
                    $level = actionability_result::LEVEL_RECOMMEND;
                    $priorityscore = 1000.0 + ($riskscore * 1.5);
                    $summaryreason = !empty($signals[0]['message'])
                        ? $signals[0]['message']
                        : "Early warning signals observed.";
                } else {
                    $level = actionability_result::LEVEL_MONITOR;
                    $priorityscore = 400.0 + $riskscore;
                    $reasons[] = "Minor indicators observed; monitor ongoing activity.";
                    $summaryreason = "Minor indicators observed; monitor ongoing activity.";
                }
            } else {
                $level = actionability_result::LEVEL_NO_ACTION;
                $priorityscore = 0.0;
                $reasons[] = "Student is progressing healthily on track.";
                $summaryreason = "No action needed. Healthy course progress.";
            }

            // Build recommendation partition if action is appropriate.
            if (in_array($level, [actionability_result::LEVEL_URGENT, actionability_result::LEVEL_RECOMMEND], true)) {
                $recpartition = $this->recommendationengine->get_primary_and_alternatives($signals, null);
                $primaryaction = $recpartition['primary'];
                $alternatives = $recpartition['alternatives'];
            }
        }

        return new actionability_result(
            userid: $userid,
            level: $level,
            priorityscore: $priorityscore,
            summaryreason: $summaryreason,
            reasons: $reasons,
            primaryaction: $primaryaction,
            alternatives: $alternatives,
            activeintervention: $activeintervention
        );
    }

    /**
     * Batch evaluate actionability for multiple students.
     *
     * @param array $studentprofiles Array of student profiles from batch_explain_students()
     * @param int $courseid Course ID
     * @param array<int, \stdClass> $activeinterventionsbyuser Pre-fetched active interventions keyed by userid
     * @return array<int, actionability_result> Keyed by userid
     */
    public function batch_evaluate(
        array $studentprofiles,
        int $courseid,
        array $activeinterventionsbyuser = []
    ): array {
        if (empty($activeinterventionsbyuser)) {
            $activeinterventionsbyuser = $this->interventionmanager->get_active_for_course_by_user($courseid);
        }

        $results = [];
        $now = time();

        foreach ($studentprofiles as $profile) {
            $uid = (int) $profile['userid'];
            $risk = new risk_result(
                score: (float) ($profile['risk_score'] ?? 0.0),
                level: $profile['status'] ?? risk_result::LEVEL_HEALTHY,
                source: $profile['source'] ?? 'course_activity_signals',
                model: $profile['model'] ?? null
            );
            $signals = $profile['signals'] ?? [];
            $activeinv = $activeinterventionsbyuser[$uid] ?? null;

            $results[$uid] = $this->evaluate(
                userid: $uid,
                courseid: $courseid,
                risk: $risk,
                signals: $signals,
                activeintervention: $activeinv,
                now: $now
            );
        }

        return $results;
    }
}
