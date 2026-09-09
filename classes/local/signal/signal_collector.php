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

namespace local_learningsuccess\local\signal;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\explanation\explanation;

/**
 * Registry and collector for modular student learning signals.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signal_collector {

    /** @var signal[] */
    private array $signals = [];

    /**
     * Constructor initializing default signal evaluators.
     *
     * @param signal[]|null $signals
     */
    public function __construct(?array $signals = null) {
        if ($signals !== null) {
            $this->signals = $signals;
        } else {
            $this->signals = [
                new inactivity_signal(),
                new overdue_signal(),
                new grade_decline_signal(),
                new completion_signal(),
                new analytics_risk_signal(),
            ];
        }
    }

    /**
     * Register an additional signal evaluator.
     *
     * @param signal $signal
     */
    public function register_signal(signal $signal): void {
        $this->signals[] = $signal;
    }

    /**
     * Collect all triggered explanations for a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return explanation[]
     */
    public function collect(int $userid, int $courseid): array {
        $results = [];

        foreach ($this->signals as $signal) {
            try {
                $explanation = $signal->evaluate($userid, $courseid);
                if ($explanation instanceof explanation) {
                    $results[] = $explanation;
                }
            } catch (\Throwable $e) {
                // Safeguard against individual evaluator exceptions.
                continue;
            }
        }

        return $results;
    }
}
