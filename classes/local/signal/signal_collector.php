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
     * @param bool $persist Whether to persist active signals to local_ls_signal table.
     * @return explanation[]
     */
    public function collect(int $userid, int $courseid, bool $persist = false): array {
        global $DB;

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

        if ($persist) {
            // Remove previous active signals for this user and course to maintain fresh state.
            $DB->delete_records('local_ls_signal', ['userid' => $userid, 'courseid' => $courseid]);

            $now = time();
            foreach ($results as $item) {
                $record = (object) [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'signal_type' => $item->get_type(),
                    'severity' => $item->get_severity(),
                    'value' => is_numeric($item->get_value()) ? (float) $item->get_value() : 0.0,
                    'metadata' => json_encode($item->get_evidence()),
                    'timecreated' => $now,
                ];
                $DB->insert_record('local_ls_signal', $record);
            }
        }

        return $results;
    }
}
