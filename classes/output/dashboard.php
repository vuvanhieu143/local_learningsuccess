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

namespace local_learningsuccess\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

/**
 * Dashboard renderable and templatable component.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {

    protected int $courseid;
    protected array $summary;
    protected array $priorities;
    protected int $groupid;
    protected array $groups;
    protected array $successstories;

    public function __construct(
        int $courseid,
        array $summary,
        array $priorities,
        int $groupid = 0,
        array $groups = [],
        array $successstories = []
    ) {
        $this->courseid = $courseid;
        $this->summary = $summary;
        $this->priorities = $priorities;
        $this->groupid = $groupid;
        $this->groups = $groups;
        $this->successstories = $successstories;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $grouplist = [];
        foreach ($this->groups as $group) {
            $grouplist[] = [
                'id' => $group->id,
                'name' => $group->name,
                'selected' => ($group->id == $this->groupid),
            ];
        }

        return [
            'courseid' => $this->courseid,
            'has_groups' => !empty($this->groups),
            'selected_group_id' => $this->groupid,
            'groups' => $grouplist,
            'has_success_stories' => !empty($this->successstories),
            'success_stories' => $this->successstories,
            'total_students' => $this->summary['total_students'],
            'healthy_count' => $this->summary['healthy_count'],
            'monitor_count' => $this->summary['monitor_count'],
            'atrisk_count' => $this->summary['atrisk_count'],
            'critical_count' => $this->summary['critical_count'],
            'active_interventions' => $this->summary['active_interventions'],
            'resolved_interventions' => $this->summary['resolved_interventions'],
            'has_priorities' => !empty($this->priorities),
            'priorities' => array_map(function ($student) {
                return [
                    'userid' => $student['userid'],
                    'courseid' => $this->courseid,
                    'fullname' => $student['fullname'],
                    'status' => $student['status'],
                    'status_label' => $student['status_label'],
                    'status_is_critical' => $student['status'] === 'critical',
                    'status_is_atrisk' => $student['status'] === 'atrisk',
                    'status_is_monitor' => $student['status'] === 'monitor',
                    'status_is_healthy' => $student['status'] === 'healthy',
                    'risk_score' => $student['risk_score'],
                    'signal_count' => $student['signal_count'],
                    'signals' => $student['signals'] ?? [],
                    'struggle_tag' => $student['struggle_tag'] ?? null,
                    'student_url' => (new \moodle_url('/local/learningsuccess/student.php', [
                        'courseid' => $this->courseid,
                        'userid' => $student['userid'],
                    ]))->out(false),
                ];
            }, $this->priorities),
        ];
    }
}

