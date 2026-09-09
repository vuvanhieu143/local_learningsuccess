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

/**
 * Deterministic recommendation engine that maps student signals to concrete intervention actions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommendation_engine {

    public const ACTION_CONTACT           = 'CONTACT';
    public const ACTION_LEARNING_RESOURCE = 'LEARNING_RESOURCE';
    public const ACTION_MISSED_ACTIVITY   = 'MISSED_ACTIVITY';
    public const ACTION_EXTENSION         = 'EXTENSION';
    public const ACTION_ADVISOR_REFERRAL  = 'ADVISOR_REFERRAL';
    public const ACTION_OTHER             = 'OTHER';

    /**
     * Generate recommendations based on collected signals.
     *
     * @param array $signals Array of signals produced by signal_collector
     * @return array List of recommended actions with priority and reason
     */
    public function recommend(array $signals): array {
        $recommendations = [];

        foreach ($signals as $sig) {
            switch ($sig['type']) {
                case 'inactivity':
                    $recommendations[] = [
                        'action' => self::ACTION_CONTACT,
                        'title' => get_string('type_contact', 'local_learningsuccess'),
                        'priority' => ($sig['severity'] === 'critical') ? 'urgent' : 'high',
                        'reason' => $sig['message'],
                        'description' => get_string('recommend_contact', 'local_learningsuccess'),
                    ];
                    break;

                case 'missed_activity':
                    $recommendations[] = [
                        'action' => self::ACTION_MISSED_ACTIVITY,
                        'title' => get_string('type_missed_activity', 'local_learningsuccess'),
                        'priority' => 'urgent',
                        'reason' => $sig['message'],
                        'description' => get_string('recommend_review_missing', 'local_learningsuccess'),
                    ];
                    $recommendations[] = [
                        'action' => self::ACTION_EXTENSION,
                        'title' => get_string('type_extension', 'local_learningsuccess'),
                        'priority' => 'medium',
                        'reason' => $sig['message'],
                        'description' => get_string('recommend_extension', 'local_learningsuccess'),
                    ];
                    break;

                case 'grade_decline':
                    $recommendations[] = [
                        'action' => self::ACTION_CONTACT,
                        'title' => get_string('type_contact', 'local_learningsuccess'),
                        'priority' => 'high',
                        'reason' => $sig['message'],
                        'description' => get_string('recommend_contact', 'local_learningsuccess'),
                    ];
                    break;

                case 'completion':
                    $recommendations[] = [
                        'action' => self::ACTION_LEARNING_RESOURCE,
                        'title' => get_string('type_learning_resource', 'local_learningsuccess'),
                        'priority' => 'medium',
                        'reason' => $sig['message'],
                        'description' => get_string('recommend_resource', 'local_learningsuccess'),
                    ];
                    break;
            }
        }

        // Multi-signal critical condition warrants academic advisor referral.
        $criticalcount = count(array_filter($signals, fn($s) => ($s['severity'] ?? '') === 'critical'));
        if ($criticalcount >= 2) {
            $recommendations[] = [
                'action' => self::ACTION_ADVISOR_REFERRAL,
                'title' => get_string('type_advisor_referral', 'local_learningsuccess'),
                'priority' => 'urgent',
                'reason' => get_string('status_critical', 'local_learningsuccess'),
                'description' => get_string('recommend_advisor', 'local_learningsuccess'),
            ];
        }

        // Deduplicate recommendations by action type.
        $deduped = [];
        foreach ($recommendations as $rec) {
            if (!isset($deduped[$rec['action']])) {
                $deduped[$rec['action']] = $rec;
            }
        }

        return array_values($deduped);
    }
}

