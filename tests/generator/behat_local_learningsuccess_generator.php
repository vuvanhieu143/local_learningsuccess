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

/**
 * Behat data generator for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_learningsuccess_generator extends behat_generator_base {
    /**
     * Get the list of creatable entities for local_learningsuccess.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'interventions' => [
                'singular' => 'intervention',
                'datagenerator' => 'intervention',
                'required' => ['user', 'course', 'teacher'],
                'switchids' => [
                    'user' => 'userid',
                    'course' => 'courseid',
                    'teacher' => 'teacherid',
                ],
            ],
        ];
    }

    /**
     * Get user ID for teacher field.
     *
     * @param string $username
     * @return int
     */
    protected function get_teacher_id(string $username): int {
        return $this->get_user_id($username);
    }
}
