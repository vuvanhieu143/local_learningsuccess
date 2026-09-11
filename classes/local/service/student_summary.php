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

namespace local_learningsuccess\local\service;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\risk\risk_result;

/**
 * Immutable Application Data Transfer Object encapsulating complete student profile insights.
 *
 * Consumed identically by page controllers, Mustache templates, and External Web Services.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_summary {

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $user User metadata (id, fullname, email)
     * @param risk_result|array $risk
     * @param array $signals
     * @param array $explanations
     * @param array $recommendations
     * @param array $interventions
     * @param array $pendingfollowups
     * @param array $notes
     * @param array $recentoutcomes
     */
    public function __construct(
        public readonly int $userid,
        public readonly int $courseid,
        public readonly array $user,
        public readonly risk_result|array $risk,
        public readonly array $signals,
        public readonly array $explanations,
        public readonly array $recommendations,
        public readonly array $interventions,
        public readonly array $pendingfollowups = [],
        public readonly array $notes = [],
        public readonly array $recentoutcomes = [],
        public readonly ?array $actionability = null
    ) {
    }

    /**
     * Get numeric risk score.
     *
     * @return int
     */
    public function get_risk_score(): int {
        if ($this->risk instanceof risk_result) {
            return (int) round($this->risk->get_score());
        }
        return (int) round($this->risk['score'] ?? $this->risk['risk_score'] ?? 0);
    }

    /**
     * Get normalized status level key.
     *
     * @return string
     */
    public function get_status(): string {
        if ($this->risk instanceof risk_result) {
            return $this->risk->get_level();
        }
        return (string) ($this->risk['level'] ?? $this->risk['status'] ?? 'healthy');
    }

    /**
     * Convert to array representation for Mustache or JSON serialization.
     *
     * @return array
     */
    public function to_array(): array {
        $status = $this->get_status();
        $riskscore = $this->get_risk_score();

        return [
            'user' => $this->user,
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'status' => $status,
            'status_label' => get_string('status_' . $status, 'local_learningsuccess'),
            'risk_score' => $riskscore,
            'signals' => $this->signals,
            'explanations' => $this->explanations,
            'recommendations' => $this->recommendations,
            'interventions' => $this->interventions,
            'pending_followups' => $this->pendingfollowups,
            'notes' => $this->notes,
            'recent_outcomes' => $this->recentoutcomes,
            'actionability' => $this->actionability,
            'timestamp' => time(),
        ];
    }
}
