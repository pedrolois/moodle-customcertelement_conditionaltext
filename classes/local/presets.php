<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace customcertelement_conditionaltext\local;

use customcertelement_conditionaltext\element;

/**
 * Ready-made rule sets that designers can load into the element form and adapt.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets {
    /** @var string[] Preset keys, in display order. */
    public const array KEYS = [
        'classification', 'passfail', 'performance', 'completion', 'achievements',
        'honours', 'language', 'department', 'group', 'cohort', 'role', 'hours', 'fasttrack', 'progress',
    ];

    /**
     * Returns a preset ready to be loaded into the form.
     *
     * @param string $key One of KEYS.
     * @param int $courseid Course used to pick a sensible default group.
     * @return array|null ['matchmode' => string, 'rules' => array[], 'defaulttext' => string] or null if unknown.
     */
    public static function get(string $key, int $courseid): ?array {
        if (!in_array($key, self::KEYS, true)) {
            return null;
        }
        $s = fn(string $id): string => get_string("preset_{$key}_{$id}", 'customcertelement_conditionaltext');
        $grade = fn(string $value, string $text, string $unit = rule_evaluator::UNIT_PERCENT): array => [
            'type' => rule_evaluator::TYPE_GRADE, 'gradeitem' => rule_evaluator::GRADE_COURSE,
            'gradeop' => 'gte', 'gradevalue' => $value, 'gradeunit' => $unit, 'text' => $text,
        ];
        $profile = fn(string $field, string $value, string $text): array => [
            'type' => rule_evaluator::TYPE_PROFILE, 'profilefield' => $field,
            'profileop' => 'equals', 'profilevalue' => $value, 'text' => $text,
        ];

        $first = element::MATCH_FIRST;
        $preset = match ($key) {
            'classification' => [$first, [
                $grade('90', $s('rule1')), $grade('75', $s('rule2')), $grade('50', $s('rule3')),
            ], ''],
            'passfail' => [$first, [$grade('50', $s('rule1'))], $s('default')],
            'performance' => [$first, [
                $grade('85', $s('rule1')), $grade('70', $s('rule2')), $grade('50', $s('rule3')),
            ], ''],
            'completion' => [$first, [
                ['type' => rule_evaluator::TYPE_COMPLETION, 'text' => $s('rule1')],
            ], $s('default')],
            'achievements' => [element::MATCH_ALL, [
                ['type' => rule_evaluator::TYPE_COMPLETION, 'text' => $s('rule1')],
                $grade('90', $s('rule2')),
                ['type' => rule_evaluator::TYPE_ALWAYS, 'text' => $s('rule3')],
            ], ''],
            'honours' => [$first, [
                $grade('90', '') + ['combine' => element::COMBINE_AND],
                ['type' => rule_evaluator::TYPE_COMPLETION, 'text' => $s('rule1')],
            ], ''],
            'language' => [$first, [
                $profile('lang', 'es', $s('rule1')), $profile('lang', 'fr', $s('rule2')),
            ], $s('default')],
            'department' => [$first, [
                $profile('department', 'Sales', $s('rule1')), $profile('department', 'Operations', $s('rule2')),
            ], ''],
            'group' => [$first, [
                ['type' => rule_evaluator::TYPE_GROUP, 'groupid' => self::first_group($courseid), 'text' => $s('rule1')],
            ], $s('default')],
            'cohort' => [$first, [
                ['type' => rule_evaluator::TYPE_COHORT, 'cohortid' => 0, 'text' => $s('rule1')],
            ], ''],
            'role' => [$first, [
                ['type' => rule_evaluator::TYPE_ROLE, 'roleid' => self::role_id('editingteacher'), 'text' => $s('rule1')],
                ['type' => rule_evaluator::TYPE_ROLE, 'roleid' => self::role_id('teacher'), 'text' => $s('rule1')],
            ], $s('default')],
            'hours' => [$first, [
                ['type' => rule_evaluator::TYPE_TIMESPENT, 'hoursop' => 'gte', 'hoursvalue' => '20', 'text' => $s('rule1')],
            ], ''],
            'progress' => [$first, [
                ['type' => rule_evaluator::TYPE_PROGRESS, 'progressop' => 'gte', 'progressvalue' => '100',
                    'text' => $s('rule1')],
                ['type' => rule_evaluator::TYPE_PROGRESS, 'progressop' => 'gte', 'progressvalue' => '50',
                    'text' => $s('rule2')],
            ], $s('default')],
            'fasttrack' => [element::MATCH_ALL, [
                ['type' => rule_evaluator::TYPE_COMPLETIONDAYS, 'daysop' => 'lte', 'daysvalue' => '30', 'text' => $s('rule1')],
            ], ''],
        };

        return [
            'matchmode' => $preset[0],
            'rules' => element::normalise_rules($preset[1]),
            'defaulttext' => $preset[2],
        ];
    }

    /**
     * Id of the role with the given archetype, or 0.
     *
     * @param string $archetype
     * @return int
     */
    private static function role_id(string $archetype): int {
        $roles = get_archetype_roles($archetype);
        return $roles ? (int)reset($roles)->id : 0;
    }

    /**
     * Id of the first group of the course, or 0.
     *
     * @param int $courseid
     * @return int
     */
    private static function first_group(int $courseid): int {
        $groups = groups_get_all_groups($courseid);
        return $groups ? (int)reset($groups)->id : 0;
    }
}
