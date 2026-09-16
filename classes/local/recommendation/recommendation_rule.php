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

namespace local_learningsuccess\local\recommendation;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface contract for deterministic recommendation rules.
 *
 * @package    local_learningsuccess
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface recommendation_rule {

    /**
     * Check if this rule matches against the student's active explanation signals.
     *
     * @param array $signals Array of explanation arrays or objects.
     * @return bool
     */
    public function matches(array $signals): bool;

    /**
     * Produce recommendation for matching signals.
     *
     * @param array $signals
     * @return recommendation
     */
    public function get_recommendation(array $signals): recommendation;
}
