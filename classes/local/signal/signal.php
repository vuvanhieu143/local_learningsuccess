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

/**
 * Interface contract for discrete student learning signals.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface signal {

    /**
     * Evaluate student learning data and return an explanation if a meaningful signal is observed.
     *
     * @param int $userid Target student user ID.
     * @param int $courseid Target course ID.
     * @return explanation|null Observable explanation or null if no signal triggered.
     */
    public function evaluate(int $userid, int $courseid): ?explanation;
}
