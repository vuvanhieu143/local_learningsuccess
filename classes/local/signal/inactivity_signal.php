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
use local_learningsuccess\local\helper\metrics_helper;

/**
 * Signal evaluator detecting course inactivity.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inactivity_signal implements signal {

    public const TYPE = 'inactivity';

    /**
     * Evaluate student inactivity in course.
     *
     * @param int $userid
     * @param int $courseid
     * @return explanation|null
     */
    public function evaluate(int $userid, int $courseid): ?explanation {
        $metrics = metrics_helper::get_student_metrics($userid, $courseid);
        $days = $metrics['inactive_days'];

        if ($days >= 14) {
            return new explanation(
                type: self::TYPE,
                severity: explanation::SEVERITY_CRITICAL,
                title: get_string('signal_inactivity_title', 'local_learningsuccess'),
                description: get_string('signal_inactivity_critical_desc', 'local_learningsuccess', $days),
                value: $days,
                evidence: ['days' => $days, 'lastaccess' => $metrics['lastaccess']]
            );
        }

        if ($days >= 7) {
            return new explanation(
                type: self::TYPE,
                severity: explanation::SEVERITY_WARNING,
                title: get_string('signal_inactivity_title', 'local_learningsuccess'),
                description: get_string('signal_inactivity_warning_desc', 'local_learningsuccess', $days),
                value: $days,
                evidence: ['days' => $days, 'lastaccess' => $metrics['lastaccess']]
            );
        }

        return null;
    }
}
