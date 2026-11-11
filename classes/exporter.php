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

namespace customcertelement_conditionaltext;

use customcertelement_conditionaltext\local\rules_field;
use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\datatypes\string_field;
use mod_customcert\export\subplugin_text_exportable;

/**
 * Template import/export support for the conditional text element.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_text_exportable {
    /**
     * Element-specific fields.
     *
     * @return array
     */
    protected function get_fields(): array {
        return parent::get_fields() + [
            'matchmode' => new enum_field([element::MATCH_FIRST, element::MATCH_ALL]),
            'rules' => new rules_field(),
            'defaulttext' => new string_field(true),
        ];
    }

    /**
     * Decodes the rules, which the generic importer stores as a JSON string.
     *
     * @param array $data
     * @return string|null
     */
    public function convert_for_import(array $data): ?string {
        $json = parent::convert_for_import($data);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $decoded['rules'] = element::normalise_rules($decoded['rules'] ?? []);
        return json_encode($decoded);
    }
}
