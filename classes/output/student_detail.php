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

use renderable;
use renderer_base;
use templatable;

/**
 * Student detail renderable and templatable component.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_detail implements renderable, templatable {
    /** @var array Student summary data */
    protected array $studentdata;

    /**
     * Constructor.
     *
     * @param array $studentdata
     */
    public function __construct(array $studentdata) {
        $this->studentdata = $studentdata;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $status = $this->studentdata['status'] ?? 'healthy';

        $interventions = array_map(function ($item) {
            $record = (object) $item;
            $outcome = strtoupper($record->outcome ?? '');
            $status = strtoupper($record->status ?? '');
            $isopen = in_array($status, ['OPEN', 'IN_PROGRESS', 'CONTACTED', 'WAITING', 'FOLLOW_UP']);

            $typekey = 'type_' . strtolower($record->type ?? '');
            $typelabel = get_string_manager()->string_exists($typekey, 'local_learningsuccess')
                ? get_string($typekey, 'local_learningsuccess')
                : ($record->type ?? '');

            $statuskey = 'status_' . strtolower($status);
            $statuslabel = get_string_manager()->string_exists($statuskey, 'local_learningsuccess')
                ? get_string($statuskey, 'local_learningsuccess')
                : $status;

            $outcomekey = 'outcome_' . strtolower($outcome);
            $outcomelabel = get_string_manager()->string_exists($outcomekey, 'local_learningsuccess')
                ? get_string($outcomekey, 'local_learningsuccess')
                : $outcome;

            $followupformatted = !empty($record->followupat)
                ? userdate((int) $record->followupat, get_string('strftimedate', 'langconfig'))
                : null;
            $notes = $this->studentdata['notes'][$record->id] ?? [];

            return [
                'id' => $record->id,
                'type' => $typelabel,
                'raw_type' => $record->type ?? '',
                'reason' => $record->reason ?? '',
                'actual_action' => $record->actual_action ?? '',
                'status' => $statuslabel,
                'raw_status' => $status,
                'outcome' => $outcomelabel,
                'raw_outcome' => $outcome,
                'outcome_is_improved' => ($outcome === 'IMPROVED'),
                'outcome_is_declined' => ($outcome === 'DECLINED'),
                'outcome_is_no_change' => ($outcome === 'NO_CHANGE'),
                'followup_due' => $followupformatted,
                'has_followup' => !empty($followupformatted),
                'notes' => $notes,
                'has_notes' => !empty($notes),
                'can_complete' => $isopen,
                'can_dismiss' => $isopen,
            ];
        }, $this->studentdata['interventions'] ?? []);

        $activeintervention = null;
        if (!empty($this->studentdata['interventions'])) {
            foreach ($this->studentdata['interventions'] as $inv) {
                $rec = (object) $inv;
                $st = strtoupper($rec->status ?? '');
                if (in_array($st, ['OPEN', 'IN_PROGRESS', 'CONTACTED', 'WAITING', 'FOLLOW_UP'])) {
                    $activeintervention = [
                        'id' => $rec->id,
                        'status' => $rec->status,
                        'type' => $rec->type,
                    ];
                    break;
                }
            }
        }

        $dismissalreasons = [
            ['value' => 'approved_leave', 'label' => get_string('reason_approved_leave', 'local_learningsuccess')],
            ['value' => 'working_offline', 'label' => get_string('reason_working_offline', 'local_learningsuccess')],
            ['value' => 'false_positive', 'label' => get_string('reason_false_positive', 'local_learningsuccess')],
            ['value' => 'not_relevant', 'label' => get_string('reason_not_relevant', 'local_learningsuccess')],
            ['value' => 'other', 'label' => get_string('reason_other', 'local_learningsuccess')],
        ];

        $signals = array_map(function ($sig) use ($dismissalreasons) {
            $item = (array) $sig;
            $type = $item['signal_type'] ?? $item['type'] ?? $item['rule'] ?? 'general';
            $msg = $item['message'] ?? $item['description'] ?? $item['title'] ?? '';
            $sev = $item['severity'] ?? 'warning';
            return array_merge($item, [
                'type' => $type,
                'signal_type' => $type,
                'message' => $msg,
                'severity' => $sev,
                'is_critical' => ($sev === 'critical'),
                'is_high' => ($sev === 'high' || $sev === 'warning'),
                'is_medium' => ($sev === 'medium' || $sev === 'info'),
                'is_low' => ($sev === 'low'),
                'dismissal_reasons' => $dismissalreasons,
            ]);
        }, $this->studentdata['signals'] ?? []);

        $profileurl = (new \moodle_url('/user/profile.php', ['id' => $this->studentdata['user']['id']]))->out(false);
        $messageurl = (new \moodle_url('/message/index.php', ['id' => $this->studentdata['user']['id']]))->out(false);

        $isanalytics = ($this->studentdata['risk']['source'] ?? '') === 'moodle_analytics';
        $risksourcelabel = $isanalytics
            ? get_string('risk_source_analytics', 'local_learningsuccess')
            : get_string('risk_source_activity', 'local_learningsuccess');

        return [
            'user' => array_merge($this->studentdata['user'], [
                'profile_url' => $profileurl,
                'message_url' => $messageurl,
            ]),
            'courseid' => $this->studentdata['courseid'],
            'profile_url' => $profileurl,
            'message_url' => $messageurl,
            'status' => $status,
            'status_label' => $this->studentdata['status_label'] ?? get_string('status_' . $status, 'local_learningsuccess'),
            'status_is_critical' => ($status === 'critical'),
            'status_is_atrisk' => ($status === 'atrisk'),
            'status_is_monitor' => ($status === 'monitor'),
            'status_is_healthy' => ($status === 'healthy'),
            'status_is_nodata' => ($status === 'nodata'),
            'risk_score' => $this->studentdata['risk_score'] ?? 0,
            'risk_source_is_analytics' => $isanalytics,
            'risk_source_label' => $risksourcelabel,
            'risk_source_tooltip' => $isanalytics
                ? get_string('risk_source_analytics', 'local_learningsuccess')
                : get_string('risk_source_tooltip', 'local_learningsuccess'),
            'risk_source_icon' => $isanalytics ? 'fa-line-chart text-info' : 'fa-tasks text-muted',
            'signals' => $signals,
            'has_signals' => !empty($signals),
            'recommendations' => $this->studentdata['recommendations'] ?? [],
            'has_recommendations' => !empty($this->studentdata['recommendations']),
            'interventions' => $interventions,
            'has_interventions' => !empty($interventions),
            'has_active_intervention' => !empty($activeintervention),
            'active_intervention' => $activeintervention,
            'actionability' => $this->studentdata['actionability'] ?? null,
            'has_actionability' => !empty($this->studentdata['actionability']),
        ];
    }
}
