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

namespace local_learningsuccess\local\risk;

/**
 * Normalized value object encapsulating student risk evaluation details.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class risk_result {
    /** @var string Healthy risk level. */
    public const LEVEL_HEALTHY = 'healthy';

    /** @var string Monitor risk level. */
    public const LEVEL_MONITOR = 'monitor';

    /** @var string At-risk level. */
    public const LEVEL_ATRISK = 'atrisk';

    /** @var string Critical risk level. */
    public const LEVEL_CRITICAL = 'critical';

    /** @var string No data available. */
    public const LEVEL_NO_DATA = 'nodata';

    /** @var float Risk priority score (0.0 to 100.0). */
    private readonly float $score;

    /** @var string Risk level constant. */
    private readonly string $level;

    /** @var string Identifying source. */
    private readonly string $source;

    /** @var string|null Specific Moodle Analytics model identifier. */
    private readonly ?string $model;

    /**
     * Constructor.
     *
     * @param float $score Risk priority score (0.0 to 100.0).
     * @param string $level Risk level constant (healthy, monitor, atrisk, critical).
     * @param string $source Identifying source ('moodle_analytics' or 'course_activity_signals').
     * @param string|null $model Specific Moodle Analytics model identifier if applicable.
     */
    public function __construct(
        float $score,
        string $level,
        string $source,
        ?string $model = null
    ) {
        $this->score = $score;
        $this->level = $level;
        $this->source = $source;
        $this->model = $model;
    }

    /**
     * Get numeric risk/priority score.
     *
     * @return float
     */
    public function get_score(): float {
        return $this->score;
    }

    /**
     * Get normalized risk level string.
     *
     * @return string
     */
    public function get_level(): string {
        return $this->level;
    }

    /**
     * Get the descriptive origin of the evaluation.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }

    /**
     * Get the machine learning model name if sourced from Moodle Analytics.
     *
     * @return string|null
     */
    public function get_model(): ?string {
        return $this->model;
    }

    /**
     * Determine if the student requires active monitoring or urgent intervention.
     *
     * @return bool
     */
    public function requires_attention(): bool {
        return in_array($this->level, [self::LEVEL_ATRISK, self::LEVEL_CRITICAL], true);
    }

    /**
     * Serialize to array representation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'score' => $this->score,
            'level' => $this->level,
            'source' => $this->source,
            'model' => $this->model,
            'requires_attention' => $this->requires_attention(),
        ];
    }
}
