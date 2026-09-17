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

namespace local_learningsuccess\event;

use local_learningsuccess\local\helper\cache_helper;

/**
 * Event observer callbacks for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {
    /**
     * Invalidate student cache when module completion is updated.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function module_completion_updated(\core\event\course_module_completion_updated $event): void {
        self::mark_dirty($event->courseid, $event->relateduserid, false);
    }

    /**
     * Invalidate student cache when an assignment submission is graded.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event): void {
        self::mark_dirty($event->courseid, $event->relateduserid, false);
    }

    /**
     * Invalidate student cache when a quiz attempt is submitted.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::mark_dirty($event->courseid, $event->userid, false);
    }

    /**
     * Invalidate course and student cache when user enrolment changes.
     *
     * @param \core\event\base $event
     */
    public static function user_enrolment_changed(\core\event\base $event): void {
        self::mark_dirty($event->courseid, $event->relateduserid, true);
    }

    /**
     * Fast dirty marking helper.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param bool $invalidatecourse Whether to purge the aggregate course cache.
     */
    protected static function mark_dirty(int $courseid, ?int $userid = null, bool $invalidatecourse = false): void {
        if ($invalidatecourse) {
            cache_helper::invalidate_course($courseid);
        }

        if ($userid) {
            cache_helper::invalidate_student($courseid, $userid);
        }
    }
}
