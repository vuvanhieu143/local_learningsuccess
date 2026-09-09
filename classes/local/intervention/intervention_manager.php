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

namespace local_learningsuccess\local\intervention;

defined('MOODLE_INTERNAL') || die();

use local_learningsuccess\local\helper\cache_helper;

/**
 * Domain manager managing student intervention lifecycle and state transitions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention_manager {

    public const STATUS_OPEN        = 'OPEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED   = 'COMPLETED';
    public const STATUS_DISMISSED   = 'DISMISSED';

    protected outcome_tracker $outcometracker;

    public function __construct(?outcome_tracker $outcometracker = null) {
        $this->outcometracker = $outcometracker ?? new outcome_tracker();
    }

    /**
     * Create a new student intervention record.
     *
     * @param int $userid Target student
     * @param int $courseid Target course
     * @param int $teacherid Intervening teacher
     * @param string $type Action type
     * @param string|null $reason Risk rationale
     * @param string|null $recommended Recommended action
     * @param string|null $actual Actual teacher action/notes
     * @param bool $sendmessage Whether to dispatch a real Moodle message to student
     * @return int Created record ID
     */
    public function create(
        int $userid,
        int $courseid,
        int $teacherid,
        string $type,
        ?string $reason = null,
        ?string $recommended = null,
        ?string $actual = null,
        bool $sendmessage = false
    ): int {
        global $DB;

        $now = time();
        $snapshot = $this->outcometracker->capture_snapshot($userid, $courseid);

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'teacherid' => $teacherid,
            'type' => $type,
            'reason' => $reason,
            'recommended_action' => $recommended,
            'actual_action' => $actual,
            'status' => self::STATUS_OPEN,
            'outcome' => outcome_tracker::OUTCOME_UNKNOWN,
            'before_snapshot' => json_encode($snapshot),
            'after_snapshot' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'completed_at' => null,
        ];

        $id = $DB->insert_record('local_ls_intervention', $record);

        if ($sendmessage && !empty($actual)) {
            $this->send_direct_message($teacherid, $userid, $actual, $courseid);
        }

        $this->invalidate_cache($courseid, $userid);

        return $id;
    }

    /**
     * Update an intervention in progress.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        global $DB;

        $record = $DB->get_record('local_ls_intervention', ['id' => $id], '*', MUST_EXIST);

        $allowedfields = ['type', 'reason', 'actual_action', 'status'];
        foreach ($allowedfields as $field) {
            if (array_key_exists($field, $data)) {
                $record->$field = $data[$field];
            }
        }

        $record->timemodified = time();
        $success = $DB->update_record('local_ls_intervention', $record);
        if ($success) {
            $this->invalidate_cache($record->courseid, $record->userid);
        }

        return $success;
    }

    /**
     * Mark an intervention as completed, capture after-snapshot, and compute outcome.
     *
     * @param int $id
     * @param string|null $actualaction
     * @return bool
     */
    public function complete(int $id, ?string $actualaction = null): bool {
        global $DB;

        $record = $DB->get_record('local_ls_intervention', ['id' => $id], '*', MUST_EXIST);

        $now = time();
        $aftersnapshot = $this->outcometracker->capture_snapshot($record->userid, $record->courseid);
        $outcome = $this->outcometracker->calculate_outcome($record->before_snapshot, $aftersnapshot);

        $record->status = self::STATUS_COMPLETED;
        $record->outcome = $outcome;
        $record->after_snapshot = json_encode($aftersnapshot);
        $record->completed_at = $now;
        $record->timemodified = $now;

        if ($actualaction !== null) {
            $record->actual_action = $actualaction;
        }

        $success = $DB->update_record('local_ls_intervention', $record);
        if ($success) {
            $this->invalidate_cache($record->courseid, $record->userid);
        }

        return $success;
    }

    /**
     * Dismiss an intervention record.
     *
     * @param int $id
     * @return bool
     */
    public function dismiss(int $id): bool {
        global $DB;

        $record = $DB->get_record('local_ls_intervention', ['id' => $id], '*', MUST_EXIST);
        $record->status = self::STATUS_DISMISSED;
        $record->timemodified = time();

        $success = $DB->update_record('local_ls_intervention', $record);
        if ($success) {
            $this->invalidate_cache($record->courseid, $record->userid);
        }

        return $success;
    }

    /**
     * Invalidate caches associated with course and student.
     *
     * @param int $courseid
     * @param int $userid
     */
    protected function invalidate_cache(int $courseid, int $userid): void {
        cache_helper::invalidate_all($courseid, $userid);
    }

    /**
     * Retrieve all interventions for a course.
     *
     * @param int $courseid
     * @return array
     */
    public function get_for_course(int $courseid): array {
        global $DB;

        return $DB->get_records('local_ls_intervention', ['courseid' => $courseid], 'timecreated DESC');
    }

    /**
     * Retrieve all interventions for a specific student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
     public function get_for_student(int $userid, int $courseid): array {
        global $DB;

        return $DB->get_records(
            'local_ls_intervention',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated DESC'
        );
    }

    /**
     * Send a direct Moodle message to the student.
     *
     * @param int $fromuserid
     * @param int $touserid
     * @param string $messagebody
     * @param int $courseid
     * @return bool
     */
    public function send_direct_message(int $fromuserid, int $touserid, string $messagebody, int $courseid): bool {
        if (empty(trim($messagebody))) {
            return false;
        }

        try {
            $userfrom = \core_user::get_user($fromuserid, '*', MUST_EXIST);
            $userto = \core_user::get_user($touserid, '*', MUST_EXIST);

            $eventdata = new \core\message\message();
            $eventdata->component         = 'moodle';
            $eventdata->name              = 'instantmessage';
            $eventdata->userfrom          = $userfrom;
            $eventdata->userto            = $userto;
            $eventdata->subject           = get_string('message_subject', 'local_learningsuccess');
            $eventdata->fullmessage       = $messagebody;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = $messagebody;
            $eventdata->notification      = 0;
            $eventdata->courseid          = $courseid;

            return (bool) message_send($eventdata);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Automatically evaluate interventions that have been open or in progress for >= $days.
     *
     * @param int $days Number of days after which to evaluate (default 7).
     * @return int Number of interventions evaluated.
     */
    public function auto_evaluate_pending_interventions(int $days = 7): int {
        global $DB;

        $thresholdtime = time() - ($days * DAYSECS);
        $sql = "status IN (:open, :inprogress) AND timecreated <= :threshold";
        $params = [
            'open' => self::STATUS_OPEN,
            'inprogress' => self::STATUS_IN_PROGRESS,
            'threshold' => $thresholdtime,
        ];

        $pending = $DB->get_records_select('local_ls_intervention', $sql, $params);
        $count = 0;

        foreach ($pending as $intervention) {
            $this->complete(
                (int) $intervention->id,
                $intervention->actual_action ?: get_string('auto_evaluated_note', 'local_learningsuccess', $days)
            );
            $count++;
        }

        return $count;
    }
}

