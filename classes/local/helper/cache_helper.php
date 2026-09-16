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

namespace local_learningsuccess\local\helper;

defined('MOODLE_INTERNAL') || die();

use cache;

/**
 * Centralized cache invalidation helper.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_helper {

    /**
     * Invalidate course summary cache, including all group-specific views.
     *
     * @param int $courseid
     */
    public static function invalidate_course(int $courseid): void {
        $cache = cache::make('local_learningsuccess', 'course_summary');
        $cache->delete($courseid);

        // Invalidate all group-specific cached views for this course.
        if (function_exists('groups_get_all_groups')) {
            $groups = groups_get_all_groups($courseid);
            if (!empty($groups)) {
                $keys = array_map(fn($g) => "{$courseid}_{$g->id}", array_values($groups));
                $cache->delete_many($keys);
            }
        }
    }

    /**
     * Invalidate student summary cache.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function invalidate_student(int $courseid, int $userid): void {
        $cache = cache::make('local_learningsuccess', 'student_summary');
        $cache->delete("{$courseid}_{$userid}");
        $cache->delete($userid);
    }

    /**
     * Invalidate both course and student caches.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function invalidate_all(int $courseid, int $userid): void {
        self::invalidate_course($courseid);
        self::invalidate_student($courseid, $userid);
    }
}
