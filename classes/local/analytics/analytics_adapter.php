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

namespace local_learningsuccess\local\analytics;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\helper\metrics_helper;

/**
 * Adapter isolating Moodle Learning Analytics core subsystem.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_adapter {

    public const STATUS_CRITICAL = 'critical';
    public const STATUS_ATRISK   = 'atrisk';
    public const STATUS_MONITOR  = 'monitor';
    public const STATUS_HEALTHY  = 'healthy';

    /**
     * Retrieve student learning status and risk score from Moodle Analytics with deterministic fallback.
     *
     * @param int $userid
     * @param int $courseid
     * @return array Structure: ['status' => string, 'risk_score' => int, 'source' => string]
     */
    public function get_student_status(int $userid, int $courseid): array {
        global $DB;

        // Step 1: Attempt to read predictions from Moodle Analytics core if available.
        if (class_exists('\core_analytics\manager')) {
            try {
                $predictions = $this->query_moodle_predictions($userid, $courseid);
                if ($predictions !== null) {
                    return $predictions;
                }
            } catch (\Throwable $e) {
                // Gracefully fallback to deterministic signal heuristics if analytics subsystem fails or models aren't trained.
            }
        }

        // Step 2: Fallback heuristic calculation based on user course interaction.
        return $this->calculate_fallback_status($userid, $courseid);
    }

    /**
     * Query core Moodle analytics predictions table if active models exist.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    protected function query_moodle_predictions(int $userid, int $courseid): ?array {
        global $DB;

        // Context ID for course.
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }

        // Query mdl_analytics_predictions if present.
        $sql = "SELECT ap.prediction, ap.predictionscore, ap.timecreated
                  FROM {analytics_predictions} ap
                  JOIN {analytics_predict_samples} aps ON aps.id = ap.sampleid
                 WHERE ap.contextid = :contextid
                   AND aps.sampleid = :userid
              ORDER BY ap.timecreated DESC";

        $record = $DB->get_record_sql($sql, ['contextid' => $context->id, 'userid' => $userid], IGNORE_MULTIPLE);
        if ($record) {
            $score = (int) round($record->predictionscore * 100);
            $status = self::STATUS_HEALTHY;
            if ($score >= 80) {
                $status = self::STATUS_CRITICAL;
            } else if ($score >= 60) {
                $status = self::STATUS_ATRISK;
            } else if ($score >= 40) {
                $status = self::STATUS_MONITOR;
            }

            return [
                'status' => $status,
                'risk_score' => $score,
                'source' => 'moodle_analytics',
            ];
        }

        return null;
    }

    /**
     * Deterministic calculation fallback when Learning Analytics models are not active.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    protected function calculate_fallback_status(int $userid, int $courseid): array {
        $metrics = metrics_helper::get_student_metrics($userid, $courseid);

        $riskscore = 0;

        // 1. Inactivity evaluation.
        $inactivedays = $metrics['inactive_days'];
        if ($inactivedays >= 14) {
            $riskscore += 45;
        } else if ($inactivedays >= 7) {
            $riskscore += 30;
        } else if ($inactivedays >= 4) {
            $riskscore += 15;
        }

        // 2. Grade check.
        if ($metrics['gradepct'] !== null) {
            $pct = $metrics['gradepct'];
            if ($pct < 40) {
                $riskscore += 40;
            } else if ($pct < 60) {
                $riskscore += 25;
            } else if ($pct < 75) {
                $riskscore += 10;
            }
        }

        // 3. Completion progress check.
        if ($metrics['total_modules'] > 0) {
            $completionpct = $metrics['completion_pct'];
            if ($completionpct < 25) {
                $riskscore += 20;
            } else if ($completionpct < 50) {
                $riskscore += 10;
            }
        }

        // Clamp risk score.
        $riskscore = min(100, max(0, $riskscore));

        // Map status.
        $status = self::STATUS_HEALTHY;
        if ($riskscore >= 70) {
            $status = self::STATUS_CRITICAL;
        } else if ($riskscore >= 50) {
            $status = self::STATUS_ATRISK;
        } else if ($riskscore >= 25) {
            $status = self::STATUS_MONITOR;
        }

        return [
            'status' => $status,
            'risk_score' => $riskscore,
            'source' => 'heuristic_engine',
        ];
    }
}

