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

namespace local_learningsuccess\local\risk;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\helper\metrics_helper;

/**
 * Fallback risk provider using observable course activity and academic signals.
 *
 * Clearly labeled as deterministic prioritisation scoring, not calibrated machine learning probabilities.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fallback_provider implements risk_provider {

    public const SOURCE_NAME = 'course_activity_signals';

    /**
     * Compute deterministic prioritisation risk score based on course activity and performance.
     *
     * @param int $userid Target student user ID.
     * @param int $courseid Target course ID.
     * @return risk_result
     */
    public function get_risk(int $userid, int $courseid): risk_result {
        $metrics = metrics_helper::get_student_metrics($userid, $courseid);

        $riskscore = 0.0;

        // 1. Inactivity signal evaluation.
        $inactivedays = $metrics['inactive_days'];
        if ($inactivedays >= 14) {
            $riskscore += 45.0;
        } else if ($inactivedays >= 7) {
            $riskscore += 30.0;
        } else if ($inactivedays >= 4) {
            $riskscore += 15.0;
        }

        // 2. Grade performance evaluation.
        if ($metrics['gradepct'] !== null) {
            $pct = $metrics['gradepct'];
            if ($pct < 40.0) {
                $riskscore += 40.0;
            } else if ($pct < 60.0) {
                $riskscore += 25.0;
            } else if ($pct < 75.0) {
                $riskscore += 10.0;
            }
        }

        // 3. Activity completion progress evaluation.
        if ($metrics['total_modules'] > 0) {
            $completionpct = $metrics['completion_pct'];
            if ($completionpct < 25.0) {
                $riskscore += 20.0;
            } else if ($completionpct < 50.0) {
                $riskscore += 10.0;
            }
        }

        // Clamp priority score between 0 and 100.
        $riskscore = min(100.0, max(0.0, $riskscore));

        // Map to normalized risk levels.
        $level = risk_result::LEVEL_HEALTHY;
        if ($riskscore >= 70.0) {
            $level = risk_result::LEVEL_CRITICAL;
        } else if ($riskscore >= 50.0) {
            $level = risk_result::LEVEL_ATRISK;
        } else if ($riskscore >= 25.0) {
            $level = risk_result::LEVEL_MONITOR;
        }

        return new risk_result(
            score: $riskscore,
            level: $level,
            source: self::SOURCE_NAME,
            model: null
        );
    }
}
