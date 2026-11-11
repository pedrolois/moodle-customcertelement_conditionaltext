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
use mod_customcert\export\datatypes\field_interface;
use mod_customcert\export\datatypes\format_exception;

/**
 * Export field holding the list of rules.
 *
 * The generic importer flattens array values, so the rules travel as a JSON string
 * and are decoded again by the element exporter.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules_field implements field_interface {
    /**
     * Import the rules.
     *
     * @param array $data
     * @return mixed JSON string of normalised rules.
     */
    public function import(array $data): mixed {
        $value = $data['value'] ?? null;
        if (!is_array($value) && !is_string($value)) {
            throw new format_exception('rules are missing');
        }
        return json_encode(element::normalise_rules($value));
    }

    /**
     * Export the rules.
     *
     * @param mixed $value
     * @return array
     */
    public function export(mixed $value): array {
        return ['value' => element::normalise_rules($value)];
    }

    /**
     * Fallback when the rules cannot be imported.
     *
     * @return mixed
     */
    public function get_fallback(): mixed {
        return '[]';
    }
}
