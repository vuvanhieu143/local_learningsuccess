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

namespace local_learningsuccess\local\outcome;

/**
 * Standardized constants and outcome representation for intervention evaluations.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome {
    /** @var string Outcome indicating improvement. */
    public const IMPROVED = 'IMPROVED';

    /** @var string Outcome indicating no measurable change. */
    public const NO_CHANGE = 'NO_CHANGE';

    /** @var string Outcome indicating metric decline. */
    public const DECLINED = 'DECLINED';

    /** @var string Outcome indicating student could not be reached. */
    public const UNABLE_TO_CONTACT = 'UNABLE_TO_CONTACT';

    /** @var string Outcome indicating intervention was not applicable. */
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';

    /** @var string Outcome unknown or pending evaluation. */
    public const UNKNOWN = 'UNKNOWN';

    /** @var string Primary outcome status constant. */
    public readonly string $status;

    /** @var float Change in risk score. */
    public readonly float $riskdelta;

    /** @var float Change in grade percentage. */
    public readonly float $gradedelta;

    /** @var float Change in completion percentage. */
    public readonly float $completiondelta;

    /** @var string Human-readable non-causal summary of observed changes. */
    public readonly string $summary;

    /**
     * Constructor.
     *
     * @param string $status Primary outcome status constant.
     * @param float $riskdelta Change in risk score (positive = risk decreased, i.e., improvement).
     * @param float $gradedelta Change in grade percentage.
     * @param float $completiondelta Change in completion percentage.
     * @param string $summary Human-readable non-causal summary of observed changes.
     */
    public function __construct(
        string $status,
        float $riskdelta = 0.0,
        float $gradedelta = 0.0,
        float $completiondelta = 0.0,
        string $summary = ''
    ) {
        $this->status = $status;
        $this->riskdelta = $riskdelta;
        $this->gradedelta = $gradedelta;
        $this->completiondelta = $completiondelta;
        $this->summary = $summary;
    }

    /**
     * Get outcome status.
     *
     * @return string
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'status' => $this->status,
            'status_label' => get_string('outcome_' . strtolower($this->status), 'local_learningsuccess'),
            'risk_delta' => $this->riskdelta,
            'grade_delta' => $this->gradedelta,
            'completion_delta' => $this->completiondelta,
            'summary' => $this->summary,
        ];
    }
}
