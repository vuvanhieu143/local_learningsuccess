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
        ];
    }
}
