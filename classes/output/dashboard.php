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
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {

    protected int $courseid;
    protected array $summary;
    protected array $priorities;
    protected int $groupid;
    protected array $groups;
    protected array $successstories;
    protected array $recentlyhandled;

    public function __construct(
        int $courseid,
        array $summary,
        array $priorities,
        int $groupid = 0,
        array $groups = [],
        array $successstories = [],
        array $recentlyhandled = []
    ) {
        $this->courseid = $courseid;
        $this->summary = $summary;
        $this->priorities = $priorities;
        $this->groupid = $groupid;
        $this->groups = $groups;
        $this->successstories = $successstories;
        $this->recentlyhandled = $recentlyhandled;
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

        $criticalcount = $this->summary['critical_count'] ?? 0;
        $atriskcount = $this->summary['atrisk_count'] ?? 0;
        $followupcount = count(array_filter($this->priorities, fn($p) => ($p['actionability_level'] ?? '') === 'follow_up'));
        $totalattention = $criticalcount + $atriskcount + $followupcount;

        $attentionobj = (object) [
            'total' => $totalattention,
            'followups' => $followupcount,
            'critical' => $criticalcount,
            'atrisk' => $atriskcount,
        ];
        $attentiondesc = get_string('what_needs_attention_desc', 'local_learningsuccess', $attentionobj);

        $recentlyhandledformatted = array_map(function ($item) {
            $studenturl = (new \moodle_url('/local/learningsuccess/student.php', [
                'courseid' => $this->courseid,
                'userid' => $item['userid'],
            ]))->out(false);

            return array_merge($item, [
                'student_url' => $studenturl,
            ]);
        }, $this->recentlyhandled);

        $lastupdatedtime = userdate($this->summary['timestamp'] ?? time(), get_string('strftimedatetime', 'langconfig'));
        $lastupdatedlabel = get_string('last_updated_at', 'local_learningsuccess', $lastupdatedtime);

        return [
            'courseid' => $this->courseid,
            'has_groups' => !empty($this->groups),
            'selected_group_id' => $this->groupid,
            'groups' => $grouplist,
            'has_attention_needed' => ($totalattention > 0),
            'attention_total' => $totalattention,
            'attention_followups' => $followupcount,
            'attention_critical' => $criticalcount,
            'attention_atrisk' => $atriskcount,
            'attention_desc' => $attentiondesc,
            'last_updated' => $lastupdatedtime,
            'last_updated_label' => $lastupdatedlabel,
            'has_recently_handled' => !empty($recentlyhandledformatted),
            'recently_handled' => $recentlyhandledformatted,
            'has_recent_improvements' => !empty($this->successstories),
            'recent_improvements' => $this->successstories,
            'has_success_stories' => !empty($this->successstories),
            'success_stories' => $this->successstories,
            'total_students' => $this->summary['total_students'],
            'healthy_count' => $this->summary['healthy_count'],
            'monitor_count' => $this->summary['monitor_count'],
            'atrisk_count' => $this->summary['atrisk_count'],
            'critical_count' => $this->summary['critical_count'],
            'nodata_count' => $this->summary['nodata_count'] ?? 0,
            'active_interventions' => $this->summary['active_interventions'],
            'resolved_interventions' => $this->summary['resolved_interventions'],
            'has_priorities' => !empty($this->priorities),
            'priorities' => array_map(function ($student) {
                $actlevel = $student['actionability_level'] ?? ($student['status'] === 'critical' ? 'urgent' : 'recommend');
                $profileurl = (new \moodle_url('/user/profile.php', ['id' => $student['userid']]))->out(false);
                $isanalytics = ($student['risk_source'] ?? '') === 'moodle_analytics';

                return [
                    'userid' => $student['userid'],
                    'courseid' => $this->courseid,
                    'fullname' => $student['fullname'],
                    'profile_url' => $profileurl,
                    'status' => $student['status'],
                    'status_label' => $student['status_label'],
                    'status_is_critical' => $student['status'] === 'critical',
                    'status_is_atrisk' => $student['status'] === 'atrisk',
                    'status_is_monitor' => $student['status'] === 'monitor',
                    'status_is_healthy' => $student['status'] === 'healthy',
                    'status_is_nodata' => $student['status'] === 'nodata',
                    'risk_source_is_analytics' => $isanalytics,
                    'risk_source_label' => $isanalytics
                        ? get_string('risk_source_analytics', 'local_learningsuccess')
                        : get_string('risk_source_activity', 'local_learningsuccess'),
                    'risk_source_tooltip' => $isanalytics
                        ? get_string('risk_source_analytics', 'local_learningsuccess')
                        : get_string('risk_source_tooltip', 'local_learningsuccess'),
                    'risk_source_icon' => $isanalytics ? 'fa-line-chart text-info' : 'fa-tasks text-muted',
                    'actionability_level' => $actlevel,
                    'actionability_level_label' => $student['actionability_level_label'] ?? $student['status_label'],
                    'is_urgent' => ($actlevel === 'urgent'),
                    'is_follow_up' => ($actlevel === 'follow_up'),
                    'is_recommend' => ($actlevel === 'recommend'),
                    'is_monitor' => ($actlevel === 'monitor'),
                    'priority_score' => $student['priority_score'] ?? $student['risk_score'],
                    'summary_reason' => $student['summary_reason'] ?? '',
                    'primary_action' => $student['primary_action'] ?? null,
                    'has_primary_action' => !empty($student['primary_action']),
                    'has_active_intervention' => !empty($student['has_active_intervention']),
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

