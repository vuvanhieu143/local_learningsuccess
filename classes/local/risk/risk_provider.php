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

namespace local_learningsuccess\local\risk;

/**
 * Interface contract for student risk providers.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface risk_provider {
    /**
     * Evaluate and retrieve risk information for a student in a course.
     *
     * @param int $userid Target student user ID.
     * @param int $courseid Target course ID.
     * @return risk_result|null Normalized risk result, or null if unresolvable.
     */
    public function get_risk(int $userid, int $courseid): ?risk_result;

    /**
     * Bulk evaluate and retrieve risk information for multiple students in a course.
     *
     * Must use the exact same calculation logic as get_risk().
     *
     * @param int[] $userids Array of target student user IDs.
     * @param int $courseid Target course ID.
     * @return array<int, risk_result> Map of userid => risk_result.
     */
    public function get_risks(array $userids, int $courseid): array;
}
