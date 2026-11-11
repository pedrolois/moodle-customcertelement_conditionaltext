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

$string['activity'] = 'Actividad';
$string['addrule'] = 'Añadir otra regla';
$string['appearance'] = 'Apariencia';
$string['badge'] = 'Insignia';
$string['chooseactivity'] = 'Elija una actividad...';
$string['choosebadge'] = 'Elija una insignia...';
$string['choosecohort'] = 'Elija una cohorte...';
$string['choosecompetency'] = 'Elija una competencia...';
$string['chooseenrolment'] = 'Elija un método de matriculación...';
$string['choosegroup'] = 'Elija un grupo...';
$string['chooserole'] = 'Elija un rol...';
$string['combine'] = 'Combinar con la regla siguiente';
$string['combine_and'] = 'Sí: la regla siguiente TAMBIÉN debe cumplirse (Y)';
$string['combine_help'] = 'Sirve para exigir varias condiciones a la vez. Si una regla se combina con la siguiente, deben cumplirse las dos y se imprime el texto de la última regla de la cadena. Por ejemplo: Regla 1 «Calificación ≥ 90 %» combinada con Regla 2 «Finalización del curso», texto «Con honores». Para expresar O, cree reglas separadas con el mismo texto.';
$string['combine_none'] = 'No';
$string['coursegrade'] = 'Total del curso';
$string['date'] = 'Fecha';
$string['datecondition'] = 'Fecha';
$string['dateop_before'] = 'es anterior a';
$string['dateop_onorafter'] = 'es igual o posterior a';
$string['datesource_completion'] = 'Fecha de finalización del curso';
$string['datesource_enrolment'] = 'Fecha de matriculación';
$string['datesource_issue'] = 'Fecha de emisión del certificado';
$string['dayscondition'] = 'Días desde la matriculación hasta la finalización';
$string['defaulttext'] = 'Texto si no se cumple ninguna regla';
$string['defaulttext_help'] = 'Se imprime cuando no se cumple ninguna regla. Déjelo vacío para no imprimir nada. Admite los mismos marcadores que los textos de las reglas.';
$string['deleterule'] = 'Eliminar regla {no}';
$string['enrolmentmethod'] = 'Método de matriculación';
$string['errorchoose'] = 'Elija una opción.';
$string['errorcombinelast'] = 'La última regla no se puede combinar con una regla siguiente.';
$string['errornumber'] = 'Introduzca un número.';
$string['errortoomanyrules'] = 'Un elemento puede tener como máximo {$a} reglas.';
$string['exampleloaded'] = 'Ejemplo cargado. Revise las reglas, adapte los textos y guarde. No se guarda nada hasta que pulse «Guardar cambios».';
$string['examples'] = 'Ejemplos';
$string['examples_help'] = 'Rellena el formulario con un conjunto de reglas ya preparado. Cargar un ejemplo sustituye las reglas que se ven en el formulario; no cambia nada hasta que guarde.';
$string['gradecondition'] = 'La calificación es';
$string['gradeitem'] = 'Elemento de calificación';
$string['hourscondition'] = 'Tiempo dedicado al curso';
$string['intro'] = 'Las reglas se comprueban de arriba abajo con los datos del estudiante que recibe el certificado. Su texto se imprime cuando se cumple la condición.';
$string['loadexample'] = 'Cargar un ejemplo';
$string['matchmode'] = 'Si se cumplen varias reglas';
$string['matchmode_all'] = 'Mostrar los textos de todas las reglas que se cumplen';
$string['matchmode_first'] = 'Mostrar solo la primera regla que se cumple';
$string['matchmode_help'] = 'Con «primera regla», ordene las reglas de la más a la menos exigente (por ejemplo 90 %, luego 75 %, luego 50 %). Con «todas las reglas», cada texto se imprime en su propia línea.';
$string['negate'] = 'Invertir';
$string['negate_help'] = 'Imprime el texto cuando la condición NO se cumple. Por ejemplo, «Grupo: Avanzado» invertido se aplica a todos los que no están en el grupo Avanzado. Las reglas que apuntan a algo que ya no existe (un grupo, actividad o insignia borrados) nunca se aplican, estén invertidas o no.';
$string['negate_label'] = 'Aplicar cuando esta condición NO se cumple';
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
$string['preset_achievements'] = 'Líneas de logros';
$string['preset_achievements_desc'] = 'Varias líneas a la vez: finalización, nota destacada y una línea para todos.';
$string['preset_achievements_rule1'] = 'Curso completado';
$string['preset_achievements_rule2'] = 'Resultado destacado: {coursegrade}';
$string['preset_achievements_rule3'] = 'Otorgado a {fullname}';
$string['preset_classification'] = 'Distinción / Mérito / Aprobado';
$string['preset_classification_desc'] = 'Clasificación por el total del curso: 90 %, 75 % y 50 %.';
$string['preset_classification_rule1'] = 'CON DISTINCIÓN';
$string['preset_classification_rule2'] = 'CON MÉRITO';
$string['preset_classification_rule3'] = 'APROBADO';
$string['preset_cohort'] = 'Según la cohorte';
$string['preset_cohort_desc'] = 'Una línea extra para los miembros de una cohorte.';
$string['preset_cohort_rule1'] = 'Programa de desarrollo profesional';
$string['preset_completion'] = 'Completado o participación';
$string['preset_completion_default'] = 'ha participado en {coursename}';
$string['preset_completion_desc'] = 'Frase distinta según se haya completado el curso o no.';
$string['preset_completion_rule1'] = 'ha completado con éxito {coursename}';
$string['preset_department'] = 'Según el departamento';
$string['preset_department_desc'] = 'Nombre del programa según el campo Departamento del perfil.';
$string['preset_department_rule1'] = 'Sales Academy';
$string['preset_department_rule2'] = 'Operations Programme';
$string['preset_fasttrack'] = 'Vía rápida';
$string['preset_fasttrack_desc'] = 'Una línea para quien completó el curso en 30 días o menos desde la matriculación.';
$string['preset_fasttrack_rule1'] = 'Completado por la vía rápida';
$string['preset_group'] = 'Según el grupo';
$string['preset_group_default'] = 'Nivel estándar';
$string['preset_group_desc'] = 'Un nivel distinto para los miembros de un grupo del curso.';
$string['preset_group_rule1'] = 'Nivel avanzado';
$string['preset_honours'] = 'Con honores (dos condiciones)';
$string['preset_honours_desc'] = 'Nota del 90 % o más Y curso completado, usando «Combinar con la regla siguiente».';
$string['preset_honours_rule1'] = 'CON HONORES';
$string['preset_hours'] = 'Horas de formación';
$string['preset_hours_desc'] = 'Una línea para quien ha dedicado 20 horas o más al curso.';
$string['preset_hours_rule1'] = 'Programa certificado de 20 horas';
$string['preset_language'] = 'Según el idioma preferido';
$string['preset_language_default'] = 'Certificate of completion';
$string['preset_language_desc'] = 'Título en el idioma del estudiante: español, francés o, si no, inglés.';
$string['preset_language_rule1'] = 'Certificado de finalización';
$string['preset_language_rule2'] = 'Certificat de réussite';
$string['preset_passfail'] = 'Aprobado o asistencia';
$string['preset_passfail_default'] = 'ha asistido a {coursename}';
$string['preset_passfail_desc'] = 'Con un total del 50 % o más dice «aprobado»; si no, «ha asistido».';
$string['preset_passfail_rule1'] = 'ha superado con éxito {coursename}';
$string['preset_performance'] = 'Excelente / Muy bien / Bien';
$string['preset_performance_desc'] = 'Nivel de rendimiento por el total del curso: 85 %, 70 % y 50 %.';
$string['preset_performance_rule1'] = 'Excelente';
$string['preset_performance_rule2'] = 'Muy bien';
$string['preset_performance_rule3'] = 'Bien';
$string['preset_progress'] = 'Según el progreso';
$string['preset_progress_default'] = 'ha comenzado el curso';
$string['preset_progress_desc'] = 'Frase según el porcentaje de actividades completadas: 100 %, 50 % o menos.';
$string['preset_progress_rule1'] = 'ha completado todas las actividades del curso';
$string['preset_progress_rule2'] = 'ha completado la mayoría de las actividades del curso';
$string['preset_role'] = 'Facilitador o participante';
$string['preset_role_default'] = 'como Participante';
$string['preset_role_desc'] = 'Los profesores reciben «Facilitador» y el resto «Participante».';
$string['preset_role_rule1'] = 'como Facilitador';
$string['privacy:metadata'] = 'El elemento Texto condicional no almacena datos personales.';
$string['profilecondition'] = 'Valor del campo';
$string['profilefield'] = 'Campo de usuario';
$string['progresscondition'] = 'Progreso del curso';
$string['rulenumber'] = 'Regla {$a}';
$string['ruletext'] = 'Texto a mostrar';
$string['ruletext_help'] = 'Texto que se imprime cuando se cumple la regla. Admite HTML y filtros (por ejemplo contenido multi-idioma) y estos marcadores: {firstname}, {lastname}, {fullname}, {coursename}, {courseshortname} y {coursegrade}. Déjelo vacío para no imprimir nada cuando se cumpla.';
$string['ruletype'] = 'Condición';
$string['ruletype_help'] = 'La condición que debe cumplir el estudiante para que se imprima el texto de esta regla:

* **Calificación**: compara la nota del estudiante (total del curso, un elemento de calificación o una actividad) con un valor, en porcentaje o en puntos. Quien no tiene nota nunca la cumple.
* **Finalización del curso**: el estudiante ha completado el curso.
* **Progreso del curso (%)**: porcentaje de actividades con seguimiento de finalización que el estudiante ha completado, igual que la barra de progreso del curso.
* **Finalización de una actividad**: el estudiante ha completado (o aprobado) una actividad con seguimiento de finalización.
* **Campo del perfil de usuario**: compara un campo estándar o personalizado del perfil con un valor. No distingue mayúsculas.
* **Grupo**: el estudiante es miembro de un grupo de este curso.
* **Cohorte**: el estudiante es miembro de una cohorte.
* **Rol en el curso**: el estudiante tiene un rol en este curso, por ejemplo Profesor sin permiso de edición.
* **Insignia obtenida**: el estudiante ha recibido una insignia del sitio o de este curso.
* **Competencia conseguida**: el estudiante ha sido calificado como competente en una competencia de este curso.
* **Método de matriculación**: el estudiante está matriculado mediante un método, por ejemplo Auto-matriculación.
* **Fecha**: compara la fecha de finalización, de emisión del certificado o de matriculación con una fecha.
* **Tiempo dedicado al curso**: horas que el estudiante ha pasado en el curso, según los registros.
* **Días en completar el curso**: días entre la matriculación y la finalización del curso.
* **Siempre**: se cumple para todos. Sirve para una línea que recibe todo el mundo (con «Mostrar los textos de todas las reglas que se cumplen») o como última regla que recoge a quien no cumplió las anteriores.

Use **Invertir** para aplicar la regla cuando la condición no se cumple, y **Combinar con la regla siguiente** para exigir varias condiciones a la vez.';
$string['type_activity'] = 'Finalización de una actividad';
$string['type_always'] = 'Siempre';
$string['type_badge'] = 'Insignia obtenida';
$string['type_cohort'] = 'Cohorte';
$string['type_competency'] = 'Competencia conseguida';
$string['type_completion'] = 'Finalización del curso';
$string['type_completiondays'] = 'Días en completar el curso';
$string['type_date'] = 'Fecha';
$string['type_enrolment'] = 'Método de matriculación';
$string['type_grade'] = 'Calificación';
$string['type_group'] = 'Grupo';
$string['type_profile'] = 'Campo del perfil de usuario';
$string['type_progress'] = 'Progreso del curso (%)';
$string['type_role'] = 'Rol en el curso';
$string['type_timespent'] = 'Tiempo dedicado al curso';
$string['unit_days'] = 'días';
$string['unit_hours'] = 'horas';
$string['unit_percent'] = '%';
$string['unit_points'] = 'puntos';
