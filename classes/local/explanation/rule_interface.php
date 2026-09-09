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

/**
 * Interface contract for all explanation rules.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface rule_interface {

    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_LOW      = 'low';

    /**
     * Evaluate rule for a specific user and course.
     *
     * @param int $userid Target student ID
     * @param int $courseid Target course ID
     * @return array|null Returns structured signal array or null if condition does not trigger:
     *                     [
     *                         'type' => string,
     *                         'severity' => string,
     *                         'value' => float|int,
     *                         'message' => string,
     *                         'metadata' => array
     *                     ]
     */
    public function evaluate(int $userid, int $courseid): ?array;
}

