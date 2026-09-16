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
 * Standard plugin library callbacks for local_learningsuccess.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the course navigation menu with a direct link to Learning Success.
 *
 * @param navigation_node $parentnode The course node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function local_learningsuccess_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    if (!get_config('local_learningsuccess', 'enabled')) {
        return;
    }

    if (has_capability('local/learningsuccess:viewcourse', $context)) {
        $url = new moodle_url('/local/learningsuccess/dashboard.php', ['courseid' => $course->id]);
        $node = navigation_node::create(
            get_string('pluginname', 'local_learningsuccess'),
            $url,
            navigation_node::NODETYPE_LEAF,
            null,
            'local_learningsuccess',
            new pix_icon('i/report', get_string('pluginname', 'local_learningsuccess'))
        );
        $node->showinflatnavigation = true;
        $parentnode->add_node($node);
    }
}
