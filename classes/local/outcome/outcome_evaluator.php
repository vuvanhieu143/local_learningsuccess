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

namespace local_learningsuccess\local\outcome;

/**
 * Domain evaluator that compares before and followup snapshots to measure intervention progress.
 *
 * Adheres strictly to Rule 7: Does not claim direct causal attribution, stating only observed metric changes.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_evaluator {
    /**
     * Compare before and after snapshots to produce a structured outcome.
     *
     * @param array|string|null $before
     * @param array|string|null $after
     * @return outcome
     */
    public function evaluate(mixed $before, mixed $after): outcome {
        if (is_string($before)) {
            $before = json_decode($before, true);
        }
        if (is_string($after)) {
            $after = json_decode($after, true);
        }

        if (empty($before) || empty($after)) {
            return new outcome(
                status: outcome::UNKNOWN,
                summary: get_string('outcome_unknown', 'local_learningsuccess')
            );
        }

        $riskbefore = (float) ($before['risk_score'] ?? 50.0);
        $riskafter = (float) ($after['risk_score'] ?? 50.0);
        $riskdelta = $riskbefore - $riskafter; // Positive if risk decreased.

        $completionbefore = (float) ($before['completion'] ?? 0.0);
        $completionafter = (float) ($after['completion'] ?? 0.0);
        $completiondelta = $completionafter - $completionbefore;

        $gradebefore = (float) ($before['grade'] ?? 0.0);
        $gradeafter = (float) ($after['grade'] ?? 0.0);
        $gradedelta = $gradeafter - $gradebefore;

        if ($riskdelta >= 10.0 || $completiondelta >= 15.0 || $gradedelta >= 10.0) {
            $status = outcome::IMPROVED;
            $summary = get_string('indicators_improved_desc', 'local_learningsuccess');
        } else if ($riskdelta <= -15.0 || $gradedelta <= -15.0) {
            $status = outcome::DECLINED;
            $summary = get_string('indicators_declined_desc', 'local_learningsuccess');
        } else {
            $status = outcome::NO_CHANGE;
            $summary = get_string('indicators_no_change_desc', 'local_learningsuccess');
        }

        return new outcome(
            status: $status,
            riskdelta: round($riskdelta, 1),
            gradedelta: round($gradedelta, 1),
            completiondelta: round($completiondelta, 1),
            summary: $summary
        );
    }

    /**
     * Compute detailed indicator evidence between snapshots.
     *
     * Keeps automatic metric movement (System Evidence) strictly separated from Teacher-Confirmed Outcome.
     *
     * @param mixed $before
     * @param mixed $after
     * @return array Detailed system evidence array.
     */
    public function evaluate_system_evidence(mixed $before, mixed $after): array {
        $outcome = $this->evaluate($before, $after);

        $b = is_string($before) ? json_decode($before, true) : (array) $before;
        $a = is_string($after) ? json_decode($after, true) : (array) $after;

        $indicators = [];
        if (!empty($b) && !empty($a)) {
            $indicators[] = [
                'name' => 'risk_score',
                'label' => 'Risk Score',
                'before' => $b['risk_score'] ?? null,
                'after' => $a['risk_score'] ?? null,
                'delta' => $outcome->riskdelta,
            ];
            $indicators[] = [
                'name' => 'completion',
                'label' => 'Completion Rate (%)',
                'before' => $b['completion'] ?? null,
                'after' => $a['completion'] ?? null,
                'delta' => $outcome->completiondelta,
            ];
            $indicators[] = [
                'name' => 'grade',
                'label' => 'Grade (%)',
                'before' => $b['grade'] ?? null,
                'after' => $a['grade'] ?? null,
                'delta' => $outcome->gradedelta,
            ];
        }

        return [
            'status' => $outcome->get_status(),
            'summary' => $outcome->summary,
            'risk_delta' => $outcome->riskdelta,
            'grade_delta' => $outcome->gradedelta,
            'completion_delta' => $outcome->completiondelta,
            'indicators' => $indicators,
        ];
    }

    /**
     * Compute aggregate intervention effectiveness statistics for a course.
     *
     * @param int $courseid
     * @return array
     */
    public function get_course_effectiveness(int $courseid): array {
        global $DB;

        $records = $DB->get_records('local_learningsuccess_int', ['courseid' => $courseid], '', 'id, outcome, status');

        $total = count($records);
        $improved = 0;
        $nochange = 0;
        $declined = 0;
        $unknown = 0;

        foreach ($records as $r) {
            $outcome = strtoupper($r->outcome ?? '');
            match ($outcome) {
                outcome::IMPROVED => $improved++,
                outcome::NO_CHANGE => $nochange++,
                outcome::DECLINED => $declined++,
                default => $unknown++,
            };
        }

        $resolved = $improved + $nochange + $declined;
        $successrate = ($resolved > 0) ? (int) round(($improved / $resolved) * 100) : 0;

        return [
            'total_interventions' => $total,
            'improved_count' => $improved,
            'no_change_count' => $nochange,
            'declined_count' => $declined,
            'unknown_count' => $unknown,
            'resolved_count' => $resolved,
            'success_rate' => $successrate,
        ];
    }
}
