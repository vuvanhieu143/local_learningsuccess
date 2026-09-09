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
 * Value object representing a practical, explainable teacher intervention recommendation.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recommendation {

    public const URGENCY_HIGH   = 'high';
    public const URGENCY_MEDIUM = 'medium';
    public const URGENCY_LOW    = 'low';

    /**
     * Constructor.
     *
     * @param string $type Intervention category type (e.g. 'checkin', 'assignment_support', 'resource_recommendation').
     * @param string $title Brief human-readable action title.
     * @param string $action Detailed practical instruction for the teacher.
     * @param string $reason Observable reason driving the recommendation.
     * @param string $urgency Urgency level ('high', 'medium', 'low').
     * @param string|null $suggestedmessage Optional empathetic draft message for student check-in.
     */
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $action,
        private readonly string $reason,
        private readonly string $urgency = self::URGENCY_MEDIUM,
        private readonly ?string $suggestedmessage = null
    ) {
    }

    /**
     * Get recommendation category type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get action description.
     *
     * @return string
     */
    public function get_action(): string {
        return $this->action;
    }

    /**
     * Get evidence reason.
     *
     * @return string
     */
    public function get_reason(): string {
        return $this->reason;
    }

    /**
     * Get urgency.
     *
     * @return string
     */
    public function get_urgency(): string {
        return $this->urgency;
    }

    /**
     * Get suggested message template.
     *
     * @return string|null
     */
    public function get_suggested_message(): ?string {
        return $this->suggestedmessage;
    }

    /**
     * Serialize to array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'action' => $this->action,
            'reason' => $this->reason,
            'urgency' => $this->urgency,
            'suggested_message' => $this->suggestedmessage,
        ];
    }
}
