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
 * Student detail renderable and templatable component.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
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
            $isOpen = in_array($status, ['OPEN', 'IN_PROGRESS']);

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
                'can_complete' => $isOpen,
                'can_dismiss' => $isOpen,
            ];
        }, $this->studentdata['interventions'] ?? []);

        return [
            'user' => $this->studentdata['user'],
            'courseid' => $this->studentdata['courseid'],
            'status' => $status,
            'status_label' => $this->studentdata['status_label'] ?? '',
            'status_is_critical' => ($status === 'critical'),
            'status_is_atrisk' => ($status === 'atrisk'),
            'status_is_monitor' => ($status === 'monitor'),
            'status_is_healthy' => ($status === 'healthy'),
            'risk_score' => $this->studentdata['risk_score'] ?? 0,
            'signals' => $this->studentdata['signals'] ?? [],
            'has_signals' => !empty($this->studentdata['signals']),
            'recommendations' => $this->studentdata['recommendations'] ?? [],
            'has_recommendations' => !empty($this->studentdata['recommendations']),
            'interventions' => $interventions,
            'has_interventions' => !empty($interventions),
        ];
    }
}

