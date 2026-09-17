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

use local_learningsuccess\local\risk\risk_result;

/**
 * Immutable Application Data Transfer Object encapsulating complete student profile insights.
 *
 * Consumed identically by page controllers, Mustache templates, and External Web Services.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_summary {
    /** @var int Target user ID. */
    public readonly int $userid;

    /** @var int Target course ID. */
    public readonly int $courseid;

    /** @var array User metadata. */
    public readonly array $user;

    /** @var risk_result|array Risk evaluation data. */
    public readonly risk_result|array $risk;

    /** @var array Underlying risk signals. */
    public readonly array $signals;

    /** @var array Explanation item records. */
    public readonly array $explanations;

    /** @var array Actionable recommendations. */
    public readonly array $recommendations;

    /** @var array Intervention history records. */
    public readonly array $interventions;

    /** @var array Pending follow-up alerts. */
    public readonly array $pendingfollowups;

    /** @var array Teacher notes on interventions. */
    public readonly array $notes;

    /** @var array Recent historical outcomes. */
    public readonly array $recentoutcomes;

    /** @var array|null Actionability result payload. */
    public readonly ?array $actionability;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $user User metadata (id, fullname)
     * @param risk_result|array $risk
     * @param array $signals
     * @param array $explanations
     * @param array $recommendations
     * @param array $interventions
     * @param array $pendingfollowups
     * @param array $notes
     * @param array $recentoutcomes
     * @param array|null $actionability
     */
    public function __construct(
        int $userid,
        int $courseid,
        array $user,
        risk_result|array $risk,
        array $signals,
        array $explanations,
        array $recommendations,
        array $interventions,
        array $pendingfollowups = [],
        array $notes = [],
        array $recentoutcomes = [],
        ?array $actionability = null
    ) {
        $this->userid = $userid;
        $this->courseid = $courseid;
        $this->user = $user;
        $this->risk = $risk;
        $this->signals = $signals;
        $this->explanations = $explanations;
        $this->recommendations = $recommendations;
        $this->interventions = $interventions;
        $this->pendingfollowups = $pendingfollowups;
        $this->notes = $notes;
        $this->recentoutcomes = $recentoutcomes;
        $this->actionability = $actionability;
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
