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

use stdClass;

/**
 * Domain entity representing an individual student intervention.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention {

    /**
     * Constructor.
     *
     * @param int $id
     * @param int $courseid
     * @param int $userid
     * @param int $teacherid
     * @param string $type
     * @param string $status
     * @param string|null $reason
     * @param string|null $action
     * @param int|null $followupat
     * @param int|null $resolvedat
     * @param string|null $outcome
     * @param int $timecreated
     * @param int $timemodified
     */
    public function __construct(
        public readonly int $id,
        public readonly int $courseid,
        public readonly int $userid,
        public readonly int $teacherid,
        public readonly string $type,
        public string $status,
        public readonly ?string $reason = null,
        public ?string $action = null,
        public ?int $followupat = null,
        public ?int $resolvedat = null,
        public ?string $outcome = null,
        public readonly int $timecreated = 0,
        public int $timemodified = 0
    ) {
    }

    /**
     * Instantiate from database record.
     *
     * @param stdClass $record
     * @return self
     */
    public static function from_record(stdClass $record): self {
        return new self(
            id: (int) $record->id,
            courseid: (int) $record->courseid,
            userid: (int) $record->userid,
            teacherid: (int) $record->teacherid,
            type: (string) $record->type,
            status: (string) $record->status,
            reason: $record->reason ?? null,
            action: $record->actual_action ?? null,
            followupat: !empty($record->followupat) ? (int) $record->followupat : null,
            resolvedat: !empty($record->resolvedat) ? (int) $record->resolvedat : (!empty($record->completed_at) ? (int) $record->completed_at : null),
            outcome: $record->outcome ?? null,
            timecreated: (int) $record->timecreated,
            timemodified: (int) $record->timemodified
        );
    }

    /**
     * Determine if intervention follow-up is due.
     *
     * @param int|null $now
     * @return bool
     */
    public function is_followup_due(?int $now = null): bool {
        $now = $now ?? time();
        return $this->followupat !== null && $this->followupat <= $now && intervention_status::is_active($this->status);
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'teacherid' => $this->teacherid,
            'type' => $this->type,
            'status' => $this->status,
            'reason' => $this->reason,
            'action' => $this->action,
            'followupat' => $this->followupat,
            'resolvedat' => $this->resolvedat,
            'outcome' => $this->outcome,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
            'is_followup_due' => $this->is_followup_due(),
        ];
    }
}
