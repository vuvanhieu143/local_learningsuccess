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

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\explanation\rules\completion;
use local_learningsuccess\local\explanation\rules\grade_decline;
use local_learningsuccess\local\explanation\rules\inactivity;
use local_learningsuccess\local\explanation\rules\missed_activity;

/**
 * Aggregator that runs all explanation rules and collects structured signals.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signal_collector {

    /** @var rule_interface[] */
    protected array $rules = [];

    /**
     * Constructor initializing standard deterministic rules.
     *
     * @param rule_interface[]|null $customrules
     */
    public function __construct(?array $customrules = null) {
        if ($customrules !== null) {
            $this->rules = $customrules;
        } else {
            $this->rules = [
                new inactivity(),
                new missed_activity(),
                new grade_decline(),
                new completion(),
            ];
        }
    }

    /**
     * Collect signals for student in course.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $persist Whether to sync to local_ls_signal table.
     * @return array
     */
    public function collect(int $userid, int $courseid, bool $persist = false): array {
        global $DB;

        $signals = [];
        foreach ($this->rules as $rule) {
            $signal = $rule->evaluate($userid, $courseid);
            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        if ($persist) {
            // Remove previous active signals for this user and course to maintain fresh state.
            $DB->delete_records('local_ls_signal', ['userid' => $userid, 'courseid' => $courseid]);

            $now = time();
            foreach ($signals as $sig) {
                $record = (object) [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'signal_type' => $sig['type'],
                    'severity' => $sig['severity'],
                    'value' => (float) ($sig['value'] ?? 0),
                    'metadata' => json_encode($sig['metadata'] ?? []),
                    'timecreated' => $now,
                ];
                $DB->insert_record('local_ls_signal', $record);
            }
        }

        return $signals;
    }
}

