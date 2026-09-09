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

use local_learningsuccess\local\analytics\analytics_adapter;

/**
 * Domain engine that synthesizes student signals into explainable risk narratives.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class explanation_engine {

    protected analytics_adapter $analyticsadapter;
    protected signal_collector $signalcollector;

    public function __construct(
        ?analytics_adapter $analyticsadapter = null,
        ?signal_collector $signalcollector = null
    ) {
        $this->analyticsadapter = $analyticsadapter ?? new analytics_adapter();
        $this->signalcollector = $signalcollector ?? new signal_collector();
    }

    /**
     * Explain why a student is at risk in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function explain_student(int $userid, int $courseid): array {
        $statusinfo = $this->analyticsadapter->get_student_status($userid, $courseid);
        $signals = $this->signalcollector->collect($userid, $courseid);

        // Sort signals by severity priority: critical > high > medium > low.
        $prioritymap = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        usort($signals, function ($a, $b) use ($prioritymap) {
            $pa = $prioritymap[$a['severity']] ?? 0;
            $pb = $prioritymap[$b['severity']] ?? 0;
            return $pb <=> $pa;
        });

        return [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => $statusinfo['status'],
            'risk_score' => $statusinfo['risk_score'],
            'status_label' => get_string('status_' . $statusinfo['status'], 'local_learningsuccess'),
            'signals' => $signals,
            'signal_count' => count($signals),
            'has_critical_signals' => !empty(array_filter($signals, fn($s) => $s['severity'] === 'critical')),
        ];
    }
}

