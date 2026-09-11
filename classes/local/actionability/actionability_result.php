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

namespace local_learningsuccess\local\actionability;

defined('MOODLE_INTERNAL') || die();

/**
 * Value object encapsulating student actionability state, priority score, and recommendations.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actionability_result {

    public const LEVEL_URGENT    = 'urgent';
    public const LEVEL_FOLLOW_UP = 'follow_up';
    public const LEVEL_RECOMMEND = 'recommend';
    public const LEVEL_MONITOR   = 'monitor';
    public const LEVEL_NO_ACTION = 'no_action';

    /** @var int Target user ID */
    private int $userid;

    /** @var string Actionability level constant */
    private string $level;

    /** @var float Priority ranking score */
    private float $priorityscore;

    /** @var string Summary reason text */
    private string $summaryreason;

    /** @var array Specific "Why now?" reason items */
    private array $reasons;

    /** @var array|null Primary recommended action */
    private ?array $primaryaction;

    /** @var array Alternative actions */
    private array $alternatives;

    /** @var \stdClass|null Current active intervention record if any */
    private ?\stdClass $activeintervention;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param string $level
     * @param float $priorityscore
     * @param string $summaryreason
     * @param array $reasons
     * @param array|null $primaryaction
     * @param array $alternatives
     * @param \stdClass|null $activeintervention
     */
    public function __construct(
        int $userid,
        string $level,
        float $priorityscore,
        string $summaryreason = '',
        array $reasons = [],
        ?array $primaryaction = null,
        array $alternatives = [],
        ?\stdClass $activeintervention = null
    ) {
        $this->userid = $userid;
        $this->level = $level;
        $this->priorityscore = $priorityscore;
        $this->summaryreason = $summaryreason;
        $this->reasons = $reasons;
        $this->primaryaction = $primaryaction;
        $this->alternatives = $alternatives;
        $this->activeintervention = $activeintervention;
    }

    /**
     * Get user ID.
     *
     * @return int
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * Get actionability level.
     *
     * @return string
     */
    public function get_level(): string {
        return $this->level;
    }

    /**
     * Get localized label for the level.
     *
     * @return string
     */
    public function get_level_label(): string {
        $key = 'priority_' . $this->level;
        return get_string($key, 'local_learningsuccess');
    }

    /**
     * Get computed priority score for work-queue sorting.
     *
     * @return float
     */
    public function get_priority_score(): float {
        return $this->priorityscore;
    }

    /**
     * Get concise summary reason.
     *
     * @return string
     */
    public function get_summary_reason(): string {
        return $this->summaryreason;
    }

    /**
     * Get detailed "Why now?" structured reasons.
     *
     * @return array
     */
    public function get_reasons(): array {
        return $this->reasons;
    }

    /**
     * Get primary action.
     *
     * @return array|null
     */
    public function get_primary_action(): ?array {
        return $this->primaryaction;
    }

    /**
     * Get alternative actions.
     *
     * @return array
     */
    public function get_alternatives(): array {
        return $this->alternatives;
    }

    /**
     * Get active intervention record if present.
     *
     * @return \stdClass|null
     */
    public function get_active_intervention(): ?\stdClass {
        return $this->activeintervention;
    }

    /**
     * Convert to serializable array format.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'userid' => $this->userid,
            'level' => $this->level,
            'level_label' => $this->get_level_label(),
            'priority_score' => $this->priorityscore,
            'summary_reason' => $this->summaryreason,
            'reasons' => $this->reasons,
            'primary_action' => $this->primaryaction,
            'alternatives' => $this->alternatives,
            'has_active_intervention' => $this->activeintervention !== null,
            'active_intervention' => $this->activeintervention ? [
                'id' => (int) $this->activeintervention->id,
                'status' => $this->activeintervention->status,
                'type' => $this->activeintervention->type ?? '',
                'followupat' => isset($this->activeintervention->followupat) ? (int) $this->activeintervention->followupat : null,
            ] : null,
        ];
    }
}
