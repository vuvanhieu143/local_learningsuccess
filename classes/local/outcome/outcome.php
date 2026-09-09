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

defined('MOODLE_INTERNAL') || die();

/**
 * Standardized constants and outcome representation for intervention evaluations.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome {

    public const IMPROVED           = 'IMPROVED';
    public const NO_CHANGE          = 'NO_CHANGE';
    public const DECLINED           = 'DECLINED';
    public const UNABLE_TO_CONTACT  = 'UNABLE_TO_CONTACT';
    public const NOT_APPLICABLE     = 'NOT_APPLICABLE';
    public const UNKNOWN            = 'UNKNOWN';

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
        public readonly string $status,
        public readonly float $riskdelta = 0.0,
        public readonly float $gradedelta = 0.0,
        public readonly float $completiondelta = 0.0,
        public readonly string $summary = ''
    ) {
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
