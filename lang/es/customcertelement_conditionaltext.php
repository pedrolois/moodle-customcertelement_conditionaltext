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
 * Spanish strings for customcertelement_conditionaltext.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addrule'] = 'Añadir otra regla';
$string['appearance'] = 'Apariencia';
$string['completion_complete'] = 'Ha completado el curso';
$string['completion_incomplete'] = 'No ha completado el curso';
$string['completionstate'] = 'Finalización del curso';
$string['coursegrade'] = 'Total del curso';
$string['defaulttext'] = 'Texto si no se cumple ninguna regla';
$string['defaulttext_help'] = 'Se imprime cuando no se cumple ninguna regla. Déjelo vacío para no imprimir nada. Admite los mismos marcadores que los textos de las reglas.';
$string['deleterule'] = 'Eliminar regla {no}';
$string['errorgradevalue'] = 'Introduzca un número.';
$string['errortoomanyrules'] = 'Un elemento puede tener como máximo {$a} reglas.';
$string['gradecondition'] = 'La calificación es';
$string['gradeitem'] = 'Elemento de calificación';
$string['intro'] = 'Las reglas se comprueban de arriba abajo con los datos del estudiante que recibe el certificado. Su texto se imprime cuando se cumple la condición.';
$string['matchmode'] = 'Si se cumplen varias reglas';
$string['matchmode_all'] = 'Mostrar los textos de todas las reglas que se cumplen';
$string['matchmode_first'] = 'Mostrar solo la primera regla que se cumple';
$string['matchmode_help'] = 'Con «primera regla», ordene las reglas de la más a la menos exigente (por ejemplo 90 %, luego 75 %, luego 50 %). Con «todas las reglas», cada texto se imprime en su propia línea.';
$string['op_contains'] = 'contiene';
$string['op_empty'] = 'está vacío';
$string['op_eq'] = '=';
$string['op_equals'] = 'es igual a';
$string['op_gt'] = '>';
$string['op_gte'] = '≥';
$string['op_lt'] = '<';
$string['op_lte'] = '≤';
$string['op_neq'] = '≠';
$string['op_notcontains'] = 'no contiene';
$string['op_notempty'] = 'no está vacío';
$string['op_notequals'] = 'no es igual a';
$string['otherwise'] = 'En otro caso';
$string['pluginname'] = 'Texto condicional';
$string['privacy:metadata'] = 'El elemento Texto condicional no almacena datos personales.';
$string['profilecondition'] = 'Valor del campo';
$string['profilefield'] = 'Campo de usuario';
$string['rulenumber'] = 'Regla {$a}';
$string['ruletext'] = 'Texto a mostrar';
$string['ruletext_help'] = 'Texto que se imprime cuando se cumple la regla. Admite HTML y filtros (por ejemplo contenido multi-idioma) y estos marcadores: {firstname}, {lastname}, {fullname}, {coursename}, {courseshortname} y {coursegrade}. Déjelo vacío para no imprimir nada cuando se cumpla.';
$string['ruletype'] = 'Condición';
$string['type_always'] = 'Siempre';
$string['type_completion'] = 'Finalización del curso';
$string['type_grade'] = 'Calificación';
$string['type_profile'] = 'Campo del perfil de usuario';
$string['unit_percent'] = '%';
$string['unit_points'] = 'puntos';
