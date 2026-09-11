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
 * Registry and collector for modular student learning signals with history and teacher dismissal overrides.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signal_collector {

    public const DISMISSAL_WINDOW_DAYS = 14;

    public const REASON_APPROVED_LEAVE  = 'approved_leave';
    public const REASON_WORKING_OFFLINE = 'working_offline';
    public const REASON_FALSE_POSITIVE  = 'false_positive';
    public const REASON_NOT_RELEVANT    = 'not_relevant';
    public const REASON_OTHER           = 'other';

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
     * Dismiss a specific signal for a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $signaltype
     * @param int $teacherid
     * @param string $reason
     * @return bool
     */
    public function dismiss_signal(
        int $userid,
        int $courseid,
        string $signaltype,
        int $teacherid,
        string $reason = self::REASON_OTHER
    ): bool {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_ls_signal', [
            'userid' => $userid,
            'courseid' => $courseid,
            'signal_type' => $signaltype,
        ]);

        if ($existing) {
            $existing->dismissed = 1;
            $existing->dismissedby = $teacherid;
            $existing->dismissedat = $now;
            $existing->dismissreason = $reason;
            $existing->active = 0;
            return $DB->update_record('local_ls_signal', $existing);
        }

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'signal_type' => $signaltype,
            'severity' => 'medium',
            'value' => 0.0,
            'metadata' => null,
            'firstseen' => $now,
            'lastseen' => $now,
            'active' => 0,
            'dismissed' => 1,
            'dismissedby' => $teacherid,
            'dismissedat' => $now,
            'dismissreason' => $reason,
            'timecreated' => $now,
        ];

        return (bool) $DB->insert_record('local_ls_signal', $record);
    }

    /**
     * Determine if a signal is currently dismissed and suppressed.
     *
     * Reactivates if dismissal has expired (> 14 days) or if severity escalated to critical.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $signaltype
     * @param string|null $currentseverity
     * @return bool
     */
    public function is_signal_dismissed(
        int $userid,
        int $courseid,
        string $signaltype,
        ?string $currentseverity = null
    ): bool {
        global $DB;

        $record = $DB->get_record('local_ls_signal', [
            'userid' => $userid,
            'courseid' => $courseid,
            'signal_type' => $signaltype,
        ]);

        if (!$record || empty($record->dismissed)) {
            return false;
        }

        $now = time();
        $dismissedat = (int) ($record->dismissedat ?? $record->timecreated);

        // Reactivation Policy 1: Expiry after 14 days.
        if (($now - $dismissedat) > (self::DISMISSAL_WINDOW_DAYS * DAYSECS)) {
            return false;
        }

        // Reactivation Policy 2: Escalation to critical if previously non-critical.
        if ($currentseverity === explanation::SEVERITY_CRITICAL && $record->severity !== explanation::SEVERITY_CRITICAL) {
            return false;
        }

        return true;
    }

    /**
     * Collect all triggered explanations for a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $persist Whether to persist active signals to local_ls_signal table.
     * @param bool $includedismissed If true, returns dismissed signals marked with is_dismissed = true
     * @return explanation[]
     */
    public function collect(
        int $userid,
        int $courseid,
        bool $persist = false,
        bool $includedismissed = false
    ): array {
        global $DB;

        $rawexplanations = [];

        foreach ($this->signals as $signal) {
            try {
                $explanation = $signal->evaluate($userid, $courseid);
                if ($explanation instanceof explanation) {
                    $rawexplanations[] = $explanation;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        $now = time();
        $results = [];

        foreach ($rawexplanations as $item) {
            $type = $item->get_type();
            $severity = $item->get_severity();
            $isdismissed = $this->is_signal_dismissed($userid, $courseid, $type, $severity);

            if ($isdismissed && !$includedismissed) {
                // Suppress dismissed signal from teacher action queue.
                continue;
            }

            $results[] = $item;

            if ($persist) {
                $existing = $DB->get_record('local_ls_signal', [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'signal_type' => $type,
                ]);

                if ($existing) {
                    $existing->lastseen = $now;
                    $existing->severity = $severity;
                    $existing->value = is_numeric($item->get_value()) ? (float) $item->get_value() : 0.0;
                    $existing->metadata = json_encode($item->get_evidence());
                    if (!$isdismissed) {
                        $existing->active = 1;
                        $existing->dismissed = 0;
                    }
                    $DB->update_record('local_ls_signal', $existing);
                } else {
                    $record = (object) [
                        'userid' => $userid,
                        'courseid' => $courseid,
                        'signal_type' => $type,
                        'severity' => $severity,
                        'value' => is_numeric($item->get_value()) ? (float) $item->get_value() : 0.0,
                        'metadata' => json_encode($item->get_evidence()),
                        'firstseen' => $now,
                        'lastseen' => $now,
                        'active' => $isdismissed ? 0 : 1,
                        'dismissed' => $isdismissed ? 1 : 0,
                        'dismissedby' => null,
                        'dismissedat' => null,
                        'dismissreason' => null,
                        'timecreated' => $now,
                    ];
                    $DB->insert_record('local_ls_signal', $record);
                }
            }
        }

        return $results;
    }
}
