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
 * Encapsulates Moodle Analytics models and predictions, providing automated fallback when models are disabled.
 *
 * Architectural Design Notes:
 * 1. Semantic Mapping: In Moodle Learning Analytics, prediction targets (such as \core\analytics\target\course_dropout)
 *    use analysers whose sample origin is typically 'user_enrolments' or 'user'. In 'user_enrolments', aps.sampleid
 *    corresponds to mdl_user_enrolments.id rather than mdl_user.id. This provider resolves both transparently
 *    via COALESCE(ue.userid, aps.sampleid).
 * 2. High-Performance Bulk Retrieval: While Moodle core provides \core_analytics\manager and \core_analytics\model,
 *    iterating through model->get_predictions() instantiates heavy individual sample and prediction objects one by one
 *    in PHP memory, which creates unacceptable overhead for cohorts of 300+ students. Encapsulating an optimized batch
 *    query inside this provider keeps page renders strictly sub-150ms while insulating the rest of the plugin from Analytics internals.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
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
        $risks = $this->get_risks([$userid], $courseid);
        return $risks[$userid] ?? $this->fallbackprovider->get_risk($userid, $courseid);
    }

    /**
     * Bulk retrieve risk evaluations for multiple students in a course.
     *
     * @param int[] $userids Array of target student user IDs.
     * @param int $courseid Target course ID.
     * @return array<int, risk_result> Map of userid => risk_result.
     */
    public function get_risks(array $userids, int $courseid): array {
        global $DB, $CFG;

        $userids = array_values(array_filter(array_unique(array_map('intval', $userids))));
        if (empty($userids)) {
            return [];
        }

        $results = [];
        $unresolveduserids = $userids;

        // Try querying batch predictions from Moodle Analytics if enabled.
        if (!empty($CFG->enableanalytics) && class_exists('\core_analytics\manager')) {
            try {
                $dbman = $DB->get_manager();
                if ($dbman->table_exists('analytics_models') && $dbman->table_exists('analytics_predictions')) {
                    $context = \context_course::instance($courseid, IGNORE_MISSING);
                    if ($context) {
                        list($uinsql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
                        $params['contextid'] = $context->id;

                        $preferredtarget = get_config('local_learningsuccess', 'analytics_model') ?: '\core\analytics\target\course_dropout';
                        $params['preferredtarget'] = $preferredtarget;

                        // Semantic Resolution:
                        // Join {user_enrolments} when sampleorigin = 'user_enrolments' to resolve actual student user ID.
                        // Order by configured/default target priority first, then recency.
                        $sql = "SELECT COALESCE(ue.userid, aps.sampleid) AS resolved_userid,
                                       ap.prediction, ap.predictionscore, am.target
                                  FROM {analytics_predictions} ap
                                  JOIN {analytics_models} am ON am.id = ap.modelid
                                  JOIN {analytics_predict_samples} aps ON aps.predictionid = ap.id
                             LEFT JOIN {user_enrolments} ue ON ue.id = aps.sampleid AND aps.sampleorigin = 'user_enrolments'
                                 WHERE ap.contextid = :contextid
                                       AND (COALESCE(ue.userid, aps.sampleid) $uinsql)
                                       AND am.enabled = 1
                              ORDER BY (CASE WHEN am.target = :preferredtarget THEN 0 ELSE 1 END) ASC,
                                       ap.timecreated DESC";

                        $predictions = $DB->get_records_sql($sql, $params);
                        foreach ($predictions as $pred) {
                            $uid = (int) $pred->resolved_userid;
                            if (isset($results[$uid])) {
                                continue;
                            }
                            $score = (float) $pred->predictionscore * 100.0;
                            $level = risk_result::LEVEL_HEALTHY;
                            if ($score >= 70.0) {
                                $level = risk_result::LEVEL_CRITICAL;
                            } else if ($score >= 50.0) {
                                $level = risk_result::LEVEL_ATRISK;
                            } else if ($score >= 25.0) {
                                $level = risk_result::LEVEL_MONITOR;
                            }

                            $results[$uid] = new risk_result(
                                score: $score,
                                level: $level,
                                source: self::SOURCE_NAME,
                                model: (string) $pred->target
                            );
                        }

                        $unresolveduserids = array_values(array_diff($userids, array_keys($results)));
                    }
                }
            } catch (\Throwable $e) {
                // Fail-safe: any analytics exception falls back gracefully to deterministic fallback_provider.
                $unresolveduserids = $userids;
            }
        }

        // For any users without analytics predictions, use fallback_provider bulk query.
        if (!empty($unresolveduserids)) {
            $fallbackrisks = $this->fallbackprovider->get_risks($unresolveduserids, $courseid);
            foreach ($fallbackrisks as $uid => $risk) {
                $results[$uid] = $risk;
            }
        }

        return $results;
    }
}
