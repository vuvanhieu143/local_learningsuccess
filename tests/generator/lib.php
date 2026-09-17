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

use local_learningsuccess\local\intervention\intervention_status;
use local_learningsuccess\local\outcome\outcome;

/**
 * Data generator for local_learningsuccess plugin.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_learningsuccess_generator extends component_generator_base {
    /**
     * Create an intervention record.
     *
     * @param array|stdClass $record
     * @return stdClass
     */
    public function create_intervention(array|stdClass $record): stdClass {
        global $DB;

        $data = (array) $record;

        if (empty($data['userid'])) {
            throw new coding_exception('userid is required to generate an intervention.');
        }
        if (empty($data['courseid'])) {
            throw new coding_exception('courseid is required to generate an intervention.');
        }
        if (empty($data['teacherid'])) {
            throw new coding_exception('teacherid is required to generate an intervention.');
        }

        $now = time();
        $status = strtolower($data['status'] ?? intervention_status::OPEN);

        $intervention = (object) [
            'userid' => (int) $data['userid'],
            'courseid' => (int) $data['courseid'],
            'teacherid' => (int) $data['teacherid'],
            'type' => strtoupper($data['type'] ?? 'CONTACT'),
            'reason' => $data['reason'] ?? null,
            'recommended_action' => $data['recommended_action'] ?? null,
            'actual_action' => $data['actual_action'] ?? null,
            'status' => $status,
            'outcome' => strtoupper($data['outcome'] ?? outcome::UNKNOWN),
            'before_snapshot_id' => null,
            'after_snapshot_id' => null,
            'system_outcome' => null,
            'teacher_outcome' => null,
            'before_snapshot' => null,
            'after_snapshot' => null,
            'timecreated' => $data['timecreated'] ?? $now,
            'timemodified' => $data['timemodified'] ?? $now,
            'completed_at' => $data['completed_at'] ?? null,
            'followupat' => $data['followupat'] ?? null,
            'resolvedat' => $data['resolvedat'] ?? null,
        ];

        $intervention->id = $DB->insert_record('local_learningsuccess_int', $intervention);

        return $intervention;
    }
}
