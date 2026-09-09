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

namespace local_learningsuccess\local\intervention;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\analytics\analytics_adapter;

/**
 * Outcome tracker that captures before/after metrics snapshots and computes intervention effectiveness.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_tracker {

    public const OUTCOME_IMPROVED = 'IMPROVED';
    public const OUTCOME_NO_CHANGE = 'NO_CHANGE';
    public const OUTCOME_DECLINED = 'DECLINED';
    public const OUTCOME_UNKNOWN  = 'UNKNOWN';

    /**
     * Capture current student metrics snapshot for a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function capture_snapshot(int $userid, int $courseid): array {
        $adapter = new analytics_adapter();
        $statusinfo = $adapter->get_student_status($userid, $courseid);
        $metrics = \local_learningsuccess\local\helper\metrics_helper::get_student_metrics($userid, $courseid);

        return [
            'risk_score' => $statusinfo['risk_score'],
            'status' => $statusinfo['status'],
            'completion' => $metrics['completion_pct'],
            'grade' => $metrics['gradepct'],
            'inactive_days' => $metrics['inactive_days'],
            'timestamp' => time(),
        ];
    }

    /**
     * Compare before and after snapshots to determine outcome.
     *
     * @param array|string|null $before
     * @param array|string|null $after
     * @return string One of OUTCOME_IMPROVED, OUTCOME_NO_CHANGE, OUTCOME_DECLINED, OUTCOME_UNKNOWN
     */
    public function calculate_outcome(mixed $before, mixed $after): string {
        if (is_string($before)) {
            $before = json_decode($before, true);
        }
        if (is_string($after)) {
            $after = json_decode($after, true);
        }

        if (empty($before) || empty($after)) {
            return self::OUTCOME_UNKNOWN;
        }

        $riskbefore = $before['risk_score'] ?? 50;
        $riskafter = $after['risk_score'] ?? 50;

        $riskdiff = $riskbefore - $riskafter; // Positive if risk decreased (improved).
        $completiondiff = ($after['completion'] ?? 0) - ($before['completion'] ?? 0);
        $gradediff = ($after['grade'] ?? 0) - ($before['grade'] ?? 0);

        if ($riskdiff >= 10 || $completiondiff >= 15 || $gradediff >= 10) {
            return self::OUTCOME_IMPROVED;
        }

        if ($riskdiff <= -10 || $gradediff <= -10) {
            return self::OUTCOME_DECLINED;
        }

        return self::OUTCOME_NO_CHANGE;
    }
}

