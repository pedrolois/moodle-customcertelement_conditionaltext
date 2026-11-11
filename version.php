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

/**
 * Version information for customcertelement_conditionaltext.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'customcertelement_conditionaltext';
$plugin->version   = 2026111102;
$plugin->requires  = 2026100500; // Moodle 5.3.
$plugin->supported = [503, 503];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
$plugin->dependencies = [
    'mod_customcert' => 2026100500, // Custom certificate 5.3 (Element System v2).
];
