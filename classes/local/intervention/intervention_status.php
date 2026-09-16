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
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class intervention_status {

    // Primary workflow states.
    public const OPEN               = 'open';
    public const CONTACTED          = 'contacted';
    public const WAITING            = 'waiting';
    public const FOLLOW_UP          = 'follow_up';
    public const COMPLETED          = 'completed';

    // Terminal / Alternative states.
    public const DISMISSED          = 'dismissed';
    public const UNABLE_TO_CONTACT  = 'unable_to_contact';
    public const NOT_APPLICABLE     = 'not_applicable';

    // Legacy compatibility aliases.
    public const RESOLVED           = self::COMPLETED;
    public const IN_PROGRESS        = self::CONTACTED;

    /**
     * Allowed state transition graph.
     *
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        self::OPEN => [
            self::CONTACTED,
            self::DISMISSED,
        ],
        self::CONTACTED => [
            self::WAITING,
            self::FOLLOW_UP,
            self::UNABLE_TO_CONTACT,
            self::COMPLETED,
            self::DISMISSED,
        ],
        self::WAITING => [
            self::FOLLOW_UP,
            self::CONTACTED,
            self::COMPLETED,
            self::DISMISSED,
        ],
        self::FOLLOW_UP => [
            self::CONTACTED,
            self::WAITING,
            self::COMPLETED,
            self::DISMISSED,
            self::NOT_APPLICABLE,
        ],
        self::UNABLE_TO_CONTACT => [
            self::CONTACTED,
            self::DISMISSED,
        ],
        self::COMPLETED => [],
        self::DISMISSED => [],
        self::NOT_APPLICABLE => [],
    ];

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
        return self::is_terminal($status);
    }

    /**
     * Determine if a status is terminal.
     *
     * @param string $status
     * @return bool
     */
    public static function is_terminal(string $status): bool {
        return in_array(strtolower($status), [
            self::COMPLETED,
            self::RESOLVED,
            self::DISMISSED,
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
    public static function can_transition(string $from, string $to): bool {
        $from = strtolower($from);
        $to = strtolower($to);

        // Alias resolution.
        if ($from === 'resolved') {
            $from = self::COMPLETED;
        } else if ($from === 'in_progress') {
            $from = self::CONTACTED;
        }

        if ($to === 'resolved') {
            $to = self::COMPLETED;
        } else if ($to === 'in_progress') {
            $to = self::CONTACTED;
        }

        if ($from === $to) {
            return true;
        }

        $allowed = self::TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Compatibility wrapper for is_valid_transition.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function is_valid_transition(string $from, string $to): bool {
        return self::can_transition($from, $to);
    }

    /**
     * Get all permitted next statuses from a current status.
     *
     * @param string $from
     * @return string[]
     */
    public static function get_valid_transitions(string $from): array {
        $from = strtolower($from);
        if ($from === 'resolved') {
            $from = self::COMPLETED;
        } else if ($from === 'in_progress') {
            $from = self::CONTACTED;
        }

        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Get all valid statuses.
     *
     * @return string[]
     */
    public static function get_all_statuses(): array {
        return [
            self::OPEN,
            self::CONTACTED,
            self::WAITING,
            self::FOLLOW_UP,
            self::COMPLETED,
            self::DISMISSED,
            self::UNABLE_TO_CONTACT,
            self::NOT_APPLICABLE,
        ];
    }

    /**
     * Get localized label for status.
     *
     * @param string $status
     * @return string
     */
    public static function get_label(string $status): string {
        $status = strtolower($status);
        $stringkey = 'status_' . $status;
        if (get_string_manager()->string_exists($stringkey, 'local_learningsuccess')) {
            return get_string($stringkey, 'local_learningsuccess');
        }
        return ucfirst(str_replace('_', ' ', $status));
    }
}
