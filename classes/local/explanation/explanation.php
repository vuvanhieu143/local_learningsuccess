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

namespace local_learningsuccess\local\explanation;

/**
 * Normalized value object encapsulating an observable learning explanation / signal evidence.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class explanation {
    /** @var string Informational severity. */
    public const SEVERITY_INFO = 'info';

    /** @var string Warning severity. */
    public const SEVERITY_WARNING = 'warning';

    /** @var string Critical severity. */
    public const SEVERITY_CRITICAL = 'critical';

    /** @var string Signal category identifier. */
    private readonly string $type;

    /** @var string Severity level. */
    private readonly string $severity;

    /** @var string Human-readable summary heading. */
    private readonly string $title;

    /** @var string Detailed contextual explanation. */
    private readonly string $description;

    /** @var mixed Underlying metric value. */
    private readonly mixed $value;

    /** @var array Contextual evidence details. */
    private readonly array $evidence;

    /**
     * Constructor.
     *
     * @param string $type Signal category identifier (e.g. 'inactivity', 'overdue', 'completion').
     * @param string $severity Severity level ('info', 'warning', 'critical').
     * @param string $title Human-readable summary heading.
     * @param string $description Detailed contextual explanation.
     * @param mixed $value Underlying metric value (e.g. 9 days, 3 overdue activities).
     * @param array $evidence Contextual evidence details (e.g. module names, deadlines).
     */
    public function __construct(
        string $type,
        string $severity,
        string $title,
        string $description,
        mixed $value = null,
        array $evidence = []
    ) {
        $this->type = $type;
        $this->severity = $severity;
        $this->title = $title;
        $this->description = $description;
        $this->value = $value;
        $this->evidence = $evidence;
    }

    /**
     * Get signal category type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Get severity level.
     *
     * @return string
     */
    public function get_severity(): string {
        return $this->severity;
    }

    /**
     * Get brief summary title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get full descriptive text.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Get underlying numeric or string value.
     *
     * @return mixed
     */
    public function get_value(): mixed {
        return $this->value;
    }

    /**
     * Get supporting evidence list.
     *
     * @return array
     */
    public function get_evidence(): array {
        return $this->evidence;
    }

    /**
     * Determine if signal represents a critical risk.
     *
     * @return bool
     */
    public function is_critical(): bool {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Serialize to array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'type' => $this->type,
            'signal_type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'message' => $this->description,
            'value' => $this->value,
            'evidence' => $this->evidence,
        ];
    }
}
