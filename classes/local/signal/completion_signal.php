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
use local_learningsuccess\local\helper\metrics_helper;

/**
 * Signal evaluator detecting stalled module completion progress.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_signal implements signal {

    public const TYPE = 'completion';

    /**
     * Evaluate completion progress for student.
     *
     * @param int $userid
     * @param int $courseid
     * @return explanation|null
     */
    public function evaluate(int $userid, int $courseid): ?explanation {
        $metrics = metrics_helper::get_student_metrics($userid, $courseid);

        if ($metrics['total_modules'] === 0) {
            return null;
        }

        $pct = (float) $metrics['completion_pct'];

        if ($pct < 25.0) {
            return new explanation(
                type: self::TYPE,
                severity: explanation::SEVERITY_CRITICAL,
                title: get_string('signal_completion_title', 'local_learningsuccess'),
                description: get_string('signal_completion_critical_desc', 'local_learningsuccess', (int) round($pct)),
                value: $pct,
                evidence: [
                    'completion_pct' => $pct,
                    'completed' => $metrics['completed_modules'],
                    'total' => $metrics['total_modules'],
                ]
            );
        }

        if ($pct < 50.0) {
            return new explanation(
                type: self::TYPE,
                severity: explanation::SEVERITY_WARNING,
                title: get_string('signal_completion_title', 'local_learningsuccess'),
                description: get_string('signal_completion_warning_desc', 'local_learningsuccess', (int) round($pct)),
                value: $pct,
                evidence: [
                    'completion_pct' => $pct,
                    'completed' => $metrics['completed_modules'],
                    'total' => $metrics['total_modules'],
                ]
            );
        }

        return null;
    }
}
