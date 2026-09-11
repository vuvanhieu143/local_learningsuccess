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

namespace local_learningsuccess\local\recommendation;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\recommendation\rules\inactivity_rule;
use local_learningsuccess\local\recommendation\rules\overdue_rule;
use local_learningsuccess\local\recommendation\rules\grade_decline_rule;
use local_learningsuccess\local\recommendation\rules\completion_rule;

/**
 * Domain engine mapping student risk evidence into practical, explainable teacher recommendations.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommendation_engine {

    /** @var recommendation_rule[] */
    private array $rules = [];

    /**
     * Constructor.
     *
     * @param recommendation_rule[]|null $rules
     */
    public function __construct(?array $rules = null) {
        if ($rules !== null) {
            $this->rules = $rules;
        } else {
            $this->rules = [
                new inactivity_rule(),
                new overdue_rule(),
                new grade_decline_rule(),
                new completion_rule(),
            ];
        }
    }

    /**
     * Build prioritized recommendations based on student explanation signals.
     *
     * @param array $signals Array of explanation arrays or objects.
     * @return array Array of recommendation arrays.
     */
    public function build(array $signals): array {
        $recommendations = [];

        foreach ($this->rules as $rule) {
            if ($rule->matches($signals)) {
                $rec = $rule->get_recommendation($signals);
                $recommendations[] = $rec->to_array();
            }
        }

        // Sort by urgency: high > medium > low.
        $urgencyweight = [
            recommendation::URGENCY_HIGH => 3,
            recommendation::URGENCY_MEDIUM => 2,
            recommendation::URGENCY_LOW => 1,
        ];

        usort($recommendations, function ($a, $b) use ($urgencyweight) {
            $wa = $urgencyweight[$a['urgency'] ?? ''] ?? 0;
            $wb = $urgencyweight[$b['urgency'] ?? ''] ?? 0;
            return $wb <=> $wa;
        });

        return $recommendations;
    }

    /**
     * Alias for build to provide a clean action verb.
     *
     * @param array $signals
     * @return array
     */
    public function recommend(array $signals): array {
        return $this->build($signals);
    }

    /**
     * Backward-compatible helper returning structured recommendation list for a student.
     *
     * @param array $signals
     * @return array
     */
    public function get_recommendations(array $signals): array {
        return $this->build($signals);
    }

    /**
     * Build and partition recommendations into one primary recommendation and alternative options.
     * Prevents duplicate recommendations if an active intervention of the same type is already in progress.
     *
     * @param array $signals
     * @param \stdClass|null $activeintervention
     * @return array{primary: ?array, alternatives: array}
     */
    public function get_primary_and_alternatives(array $signals, ?\stdClass $activeintervention = null): array {
        $all = $this->build($signals);

        if (empty($all)) {
            return [
                'primary' => null,
                'alternatives' => [],
            ];
        }

        // If an intervention is currently active, avoid proposing an identical intervention as primary.
        if ($activeintervention !== null) {
            $activetype = strtolower($activeintervention->type ?? '');
            $filtered = [];
            $deferred = [];

            foreach ($all as $rec) {
                $rectype = strtolower($rec['type'] ?? '');
                // Normalize checkin / contact type equivalence.
                $isduplicate = ($rectype === $activetype)
                    || ($rectype === 'checkin' && in_array($activetype, ['contact', 'direct_message', 'checkin'], true));

                if ($isduplicate) {
                    $rec['is_active_duplicate'] = true;
                    $deferred[] = $rec;
                } else {
                    $filtered[] = $rec;
                }
            }

            // If non-duplicate recommendations exist, prioritize them.
            if (!empty($filtered)) {
                $all = array_merge($filtered, $deferred);
            }
        }

        $primary = $all[0] ?? null;
        $alternatives = array_slice($all, 1);

        return [
            'primary' => $primary,
            'alternatives' => array_values($alternatives),
        ];
    }

    /**
     * Get default follow-up window in seconds for a given intervention type.
     *
     * Check-in: 3 days
     * Meeting requested: 3 days
     * Assignment support: 7 days
     * Learning resource / default: 7 days
     *
     * @param string $type Intervention type
     * @return int Duration in seconds
     */
    public static function get_default_followup_duration(string $type): int {
        return match (strtolower($type)) {
            'checkin', 'contact', 'direct_message' => 3 * DAYSECS,
            'meeting', 'advisor_referral' => 3 * DAYSECS,
            'extension', 'assignment_support', 'missed_activity' => 7 * DAYSECS,
            'learning_resource', 'resource', 'progress_monitoring' => 7 * DAYSECS,
            default => 7 * DAYSECS,
        };
    }
}


