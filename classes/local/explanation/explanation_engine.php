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
 * @copyright  2026 Learning Success Team
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
}
