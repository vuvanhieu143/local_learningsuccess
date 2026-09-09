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

namespace local_learningsuccess\local\intervention;

defined('MOODLE_INTERNAL') || die();

/**
 * Standardized constants and state-machine transitions for the intervention lifecycle.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention_status {

    // Primary workflow states.
    public const OPEN               = 'open';
    public const CONTACTED          = 'contacted';
    public const WAITING            = 'waiting';
    public const FOLLOW_UP          = 'follow_up';
    public const RESOLVED           = 'resolved';

    // Terminal / Alternative states.
    public const UNABLE_TO_CONTACT  = 'unable_to_contact';
    public const NOT_APPLICABLE     = 'not_applicable';

    // Legacy compatibility aliases.
    public const IN_PROGRESS        = 'in_progress';
    public const COMPLETED          = 'completed';
    public const DISMISSED          = 'dismissed';

    /**
     * Determine if a status is considered active / unresolved.
     *
     * @param string $status
     * @return bool
     */
    public static function is_active(string $status): bool {
        return in_array(strtolower($status), [
            self::OPEN,
            self::CONTACTED,
            self::WAITING,
            self::FOLLOW_UP,
            self::IN_PROGRESS,
        ], true);
    }

    /**
     * Determine if a status is considered terminal / closed.
     *
     * @param string $status
     * @return bool
     */
    public static function is_closed(string $status): bool {
        return in_array(strtolower($status), [
            self::RESOLVED,
            self::COMPLETED,
            self::DISMISSED,
            self::UNABLE_TO_CONTACT,
            self::NOT_APPLICABLE,
        ], true);
    }

    /**
     * Validate whether a state transition is permitted.
     *
     * @param string $from Current status.
     * @param string $to Target status.
     * @return bool
     */
    public static function is_valid_transition(string $from, string $to): bool {
        $from = strtolower($from);
        $to = strtolower($to);

        if ($from === $to) {
            return true;
        }

        // Terminal states cannot transition to open states without reopening.
        if (self::is_closed($from) && self::is_active($to)) {
            return false;
        }

        return true;
    }
}
