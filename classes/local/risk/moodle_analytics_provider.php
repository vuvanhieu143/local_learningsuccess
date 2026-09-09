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

/**
 * Risk provider interfacing directly with Moodle Learning Analytics core subsystem.
 *
 * Encapsulates Moodle Analytics tables and models, providing automated fallback when models are disabled.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_analytics_provider implements risk_provider {

    public const SOURCE_NAME = 'moodle_analytics';

    /** @var fallback_provider */
    private fallback_provider $fallbackprovider;

    /**
     * Constructor.
     *
     * @param fallback_provider|null $fallbackprovider
     */
    public function __construct(?fallback_provider $fallbackprovider = null) {
        $this->fallbackprovider = $fallbackprovider ?? new fallback_provider();
    }

    /**
     * Retrieve risk evaluation from Moodle Analytics, falling back gracefully if unavailable.
     *
     * @param int $userid
     * @param int $courseid
     * @return risk_result
     */
    public function get_risk(int $userid, int $courseid): risk_result {
        $analyticsrisk = $this->query_moodle_analytics($userid, $courseid);

        if ($analyticsrisk !== null) {
            return $analyticsrisk;
        }

        return $this->fallbackprovider->get_risk($userid, $courseid);
    }

    /**
     * Safely query Moodle Analytics core predictions table with exception isolation.
     *
     * @param int $userid
     * @param int $courseid
     * @return risk_result|null
     */
    protected function query_moodle_analytics(int $userid, int $courseid): ?risk_result {
        global $DB, $CFG;

        if (empty($CFG->enableanalytics)) {
            return null;
        }

        try {
            // Check if analytics_models and analytics_predictions exist before executing.
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('analytics_models') || !$dbman->table_exists('analytics_predictions')) {
                return null;
            }

            // Find latest prediction for students at risk in this course.
            $sql = "SELECT ap.id, ap.prediction, ap.predictionscore, am.target
                      FROM {analytics_predictions} ap
                      JOIN {analytics_models} am ON am.id = ap.modelid
                      JOIN {analytics_predict_samples} aps ON aps.predictionid = ap.id
                     WHERE ap.contextid = :contextid
                       AND aps.sampleid = :sampleid
                       AND am.enabled = 1
                  ORDER BY ap.timecreated DESC";

            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                return null;
            }

            $prediction = $DB->get_record_sql($sql, [
                'contextid' => $context->id,
                'sampleid' => $userid,
            ], IGNORE_MULTIPLE);

            if ($prediction) {
                $score = (float) $prediction->predictionscore * 100.0;
                $level = risk_result::LEVEL_HEALTHY;

                if ($score >= 70.0) {
                    $level = risk_result::LEVEL_CRITICAL;
                } else if ($score >= 50.0) {
                    $level = risk_result::LEVEL_ATRISK;
                } else if ($score >= 25.0) {
                    $level = risk_result::LEVEL_MONITOR;
                }

                return new risk_result(
                    score: $score,
                    level: $level,
                    source: self::SOURCE_NAME,
                    model: (string) $prediction->target
                );
            }
        } catch (\Throwable $e) {
            // Gracefully ignore database or schema mismatches in core analytics.
            return null;
        }

        return null;
    }
}
