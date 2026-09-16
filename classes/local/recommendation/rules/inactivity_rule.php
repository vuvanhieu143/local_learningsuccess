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

namespace local_learningsuccess\local\recommendation\rules;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\recommendation\recommendation;
use local_learningsuccess\local\recommendation\recommendation_rule;

/**
 * Recommendation rule mapping inactivity signals to supportive check-in actions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactivity_rule implements recommendation_rule {

    /**
     * Check if inactivity signal is present.
     *
     * @param array $signals
     * @return bool
     */
    public function matches(array $signals): bool {
        foreach ($signals as $s) {
            $type = is_array($s) ? ($s['type'] ?? $s['rule'] ?? '') : $s->get_type();
            if ($type === 'inactivity') {
                return true;
            }
        }
        return false;
    }

    /**
     * Produce check-in recommendation.
     *
     * @param array $signals
     * @return recommendation
     */
    public function get_recommendation(array $signals): recommendation {
        $days = 7;
        $isCritical = false;

        foreach ($signals as $s) {
            $type = is_array($s) ? ($s['type'] ?? $s['rule'] ?? '') : $s->get_type();
            if ($type === 'inactivity') {
                $days = is_array($s) ? ($s['value'] ?? 7) : $s->get_value();
                $sev = is_array($s) ? ($s['severity'] ?? '') : $s->get_severity();
                $isCritical = ($sev === 'critical');
                break;
            }
        }

        return new recommendation(
            type: 'checkin',
            title: get_string('action_contact_student', 'local_learningsuccess'),
            action: get_string('recommend_contact', 'local_learningsuccess'),
            reason: get_string('signal_inactivity_warning_desc', 'local_learningsuccess', $days),
            urgency: $isCritical ? recommendation::URGENCY_HIGH : recommendation::URGENCY_MEDIUM,
            suggestedmessage: get_string('default_checkin_message', 'local_learningsuccess')
        );
    }
}
