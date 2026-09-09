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

use local_learningsuccess\local\helper\metrics_helper;
use local_learningsuccess\local\risk\moodle_analytics_provider;
use local_learningsuccess\local\risk\risk_provider;

/**
 * Service capturing standardized before and followup metrics snapshots for interventions.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_service {

    /** @var risk_provider */
    private risk_provider $riskprovider;

    /**
     * Constructor.
     *
     * @param risk_provider|null $riskprovider
     */
    public function __construct(?risk_provider $riskprovider = null) {
        $this->riskprovider = $riskprovider ?? new moodle_analytics_provider();
    }

    /**
     * Capture snapshot array for a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function capture(int $userid, int $courseid): array {
        $risk = $this->riskprovider->get_risk($userid, $courseid);
        $metrics = metrics_helper::get_student_metrics($userid, $courseid);

        return [
            'risk_score' => round($risk->get_score(), 1),
            'risk_level' => $risk->get_level(),
            'status' => $risk->get_level(),
            'completion' => (float) $metrics['completion_pct'],
            'grade' => ($metrics['gradepct'] !== null) ? (float) $metrics['gradepct'] : 0.0,
            'inactive_days' => (int) $metrics['inactive_days'],
            'timestamp' => time(),
        ];
    }

    /**
     * Capture and persist snapshot to local_ls_snapshot table.
     *
     * @param int $interventionid
     * @param int $userid
     * @param int $courseid
     * @param string $phase 'before' or 'followup'
     * @return int Created snapshot ID
     */
    public function record_snapshot(int $interventionid, int $userid, int $courseid, string $phase): int {
        global $DB;

        $data = $this->capture($userid, $courseid);

        $record = (object) [
            'interventionid' => $interventionid,
            'phase' => $phase,
            'riskscore' => $data['risk_score'],
            'risklevel' => $data['risk_level'],
            'completion' => $data['completion'],
            'grade' => $data['grade'],
            'activitylevel' => max(0, 30 - $data['inactive_days']),
            'overduecount' => 0,
            'timecreated' => $data['timestamp'],
        ];

        return $DB->insert_record('local_ls_snapshot', $record);
    }
}
