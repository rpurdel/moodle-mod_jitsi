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

/**
 * Prints a particular instance of jitsi
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_jitsi
 * @copyright  2019 Sergio Comerón <sergiocomeron@icloud.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

// Allow CORS requests.
header('Access-Control-Allow-Origin: *');

global $USER;

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);
$state = optional_param('state', null, PARAM_TEXT);
$deletejitsirecordid = optional_param('deletejitsirecordid', 0, PARAM_INT);
$hidejitsirecordid = optional_param('hidejitsirecordid', 0, PARAM_INT);
$showjitsirecordid = optional_param('showjitsirecordid', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('jitsi', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);
    $sesskey = optional_param('sesskey', null, PARAM_TEXT);
} else if ($n) {
    $jitsi = $DB->get_record('jitsi', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $jitsi->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('jitsi', $jitsi->id, $course->id, false, MUST_EXIST);
} else if ($state) {
    $paramdecode = base64urldecode($state);
    $parametrosarray = explode("&", $paramdecode);
    $idarray = $parametrosarray[0];
    $deletejitsirecordidarray = $parametrosarray[1];
    $hidejitsirecordidarray = $parametrosarray[2];
    $showjitsirecordidarray = $parametrosarray[3];
    $sesskeyarray = $parametrosarray[4];
    $statesesarray = $parametrosarray[5];
    $ida = explode("=", $idarray);
    $deletejitsirecordida = explode("=", $deletejitsirecordidarray);
    $hidejitsirecordida = explode("=", $hidejitsirecordidarray);
    $showjitsirecordida = explode("=", $showjitsirecordidarray);
    $sesskeya = explode("=", $sesskeyarray);
    $statesesa = explode("=", $statesesarray);
    $id = $ida[1];
    $deletejitsirecordid = $deletejitsirecordida[1];
    $hidejitsirecordid = $hidejitsirecordida[1];
    $showjitsirecordid = $showjitsirecordida[1];
    $sesskey = $sesskeya[1];
    $stateses = $statesesa[1];
    $cm = get_coursemodule_from_id('jitsi', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new \moodle_exception('Unable to find jitsi');
}

require_login($course, true, $cm);
$event = \mod_jitsi\event\course_module_viewed::create([
  'objectid' => $PAGE->cm->instance,
  'context' => $PAGE->context,
]);

$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $jitsi);
$event->trigger();
$PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);

$PAGE->set_title(format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));

if ($deletejitsirecordid && confirm_sesskey($sesskey)) {
    marktodelete($deletejitsirecordid, 1);
    $record = $DB->get_record('jitsi_record', ['id' => $deletejitsirecordid]);
    $source = $DB->get_record('jitsi_source_record', ['id' => $record->source]);
    $event = \mod_jitsi\event\jitsi_delete_record::create([
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
        'other' => ['record' => $deletejitsirecordid, 'link' => $source->link],
    ]);
    $event->add_record_snapshot('course', $PAGE->course);
    $event->add_record_snapshot($PAGE->cm->modname, $jitsi);
    $event->trigger();

    redirect($PAGE->url, get_string('deleted'));
}

if ($hidejitsirecordid && confirm_sesskey($sesskey)) {
    $record = $DB->get_record('jitsi_record', ['id' => $hidejitsirecordid]);
    $record->visible = 0;
    $DB->update_record('jitsi_record', $record);
    redirect($PAGE->url, get_string('updated', 'jitsi'));
}

if ($showjitsirecordid && confirm_sesskey($sesskey)) {
    $record = $DB->get_record('jitsi_record', ['id' => $showjitsirecordid]);
    $record->visible = 1;
    $DB->update_record('jitsi_record', $record);
    redirect($PAGE->url, get_string('updated', 'jitsi'));
}

$context = context_module::instance($cm->id);

$cm = get_coursemodule_from_id('jitsi', $id);
$cminfo = \cm_info::create($cm);

if (!has_capability('mod/jitsi:view', $context)) {
    notice(get_string('noviewpermission', 'jitsi'));
}
$courseid = $course->id;
$context = context_course::instance($courseid);
$roles = get_user_roles($context, $USER->id);

$rolestr[] = null;
foreach ($roles as $role) {
    $rolestr[] = $role->shortname;
}

$moderation = false;
if (has_capability('mod/jitsi:moderation', $context)) {
    $moderation = true;
}

$nom = null;
switch ($CFG->jitsi_id) {
    case 'username':
        $nom = $USER->username;
        break;
    case 'nameandsurname':
        $nom = $USER->firstname.' '.$USER->lastname;
        break;
    case 'alias':
        break;
}
$sessionoptionsparam = ['$course->shortname', '$jitsi->id', '$jitsi->name'];
$fieldssessionname = $CFG->jitsi_sesionname;

$allowed = explode(',', $fieldssessionname);
$max = count($allowed);

$sesparam = '';

$errorborrado = false;
if ($jitsi->sessionwithtoken == 0) {
    $courseshortname = $course->shortname;
    $jitsiid = $jitsi->id;
    $jitsiname = $jitsi->name;
} else {
    $sql = "select * from {jitsi} where tokeninterno = '".$jitsi->tokeninvitacion."'";
    $jitsiinvitado = $DB->get_record_sql($sql);
    if ($jitsiinvitado != null) {
        $courseinvitado = $DB->get_record('course', ['id' => $jitsiinvitado->course]);
        $courseshortname = $courseinvitado->shortname;
        $jitsiid = $jitsiinvitado->id;
        $jitsiname = $jitsiinvitado->name;
    } else {
        $errorborrado = true;
    }
}

if ($errorborrado == false) {
    $optionsseparator = ['.', '-', '_', ''];
    for ($i = 0; $i < $max; $i++) {
        if ($i != $max - 1) {
            if ($allowed[$i] == 0) {
                $sesparam .= string_sanitize($courseshortname).$optionsseparator[$CFG->jitsi_separator];
            } else if ($allowed[$i] == 1) {
                $sesparam .= $jitsiid.$optionsseparator[$CFG->jitsi_separator];
            } else if ($allowed[$i] == 2) {
                $sesparam .= string_sanitize($jitsiname).$optionsseparator[$CFG->jitsi_separator];
            }
        } else {
            if ($allowed[$i] == 0) {
                $sesparam .= string_sanitize($courseshortname);
            } else if ($allowed[$i] == 1) {
                $sesparam .= $jitsiid;
            } else if ($allowed[$i] == 2) {
                $sesparam .= string_sanitize($jitsiname);
            }
        }
    }
    $avatar = $CFG->wwwroot.'/user/pix.php/'.$USER->id.'/f1.jpg';
    $urlparams = [
        'avatar' => $avatar,
        'nom' => $nom,
        'ses' => $sesparam,
        'courseid' => $course->id,
        'cmid' => $id,
        't' => $moderation,
    ];
    $today = getdate();
}

if (!$deletejitsirecordid) {
    echo $OUTPUT->header();
}

$cm = get_coursemodule_from_id('jitsi', $id, 0, false, MUST_EXIST);
update_completition($cm);
if ($CFG->branch == 311) {
    if (!$deletejitsirecordid) {
        echo $OUTPUT->heading($jitsi->name);
    }
    $completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);
    echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);
}

$contextmodule = context_module::instance($cm->id);

$sqllastparticipating = 'select timecreated from {logstore_standard_log} where contextid = '
    .$contextmodule->id.' and (action = \'participating\' or action = \'enter\') order by timecreated DESC limit 1';
$usersconnected = $DB->get_record_sql($sqllastparticipating);
if ($usersconnected != null) {
    if ((getdate()[0] - $usersconnected->timecreated) > 72 ) {
        $jitsi->numberofparticipants = 0;
        $DB->update_record('jitsi', $jitsi);
    }
}
if ($usersconnected != null) {
    if ($jitsi->numberofparticipants == 0 && (getdate()[0] - $usersconnected->timecreated) > 72 ) {
        $jitsi->sourcerecord = null;
        $DB->update_record('jitsi', $jitsi);
    }
}
if ($errorborrado) {
    echo "<div class=\"alert alert-danger\" role=\"alert\">";

    echo get_string('sessiondeleted', 'jitsi');
    echo "</div>";
    echo $OUTPUT->footer();
    die();
}
echo " ";
echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\"
     class=\"bi bi-person-workspace\" viewBox=\"0 0 16 16\">";
echo "<path d=\"M4 16s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H4Zm4-5.95a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z\"/>";
echo "<path d=\"M2 1a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 1.5 14h.653a5.373 5.373 0 0 1 1.066-2H1V3a1 1 0 0 1 1-1h12a1 1 0 0 1
     1 1v9h-2.219c.554.654.89 1.373 1.066 2h.653a1.5 1.5 0 0 0 1.5-1.5V3a2 2 0 0 0-2-2H2Z\"/>";
echo "</svg>";
echo (" ".$jitsi->numberofparticipants." ".get_string('connectedattendeesnow', 'jitsi'));
echo "<p></p>";
if ($jitsi->sessionwithtoken) {
    echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\"
        class=\"bi bi-share\" viewBox=\"0 0 16 16\">";
    echo "<path d=\"M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1
        0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5
        1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z\"/>";
    echo "</svg> ";
    $sql = "select * from {jitsi} where tokeninterno = '".$jitsi->tokeninvitacion."'";
    $jitsimaster = $DB->get_record_sql($sql);
    $coursemaster = $DB->get_record('course', ['id' => $jitsimaster->course]);
    echo get_string('sessionshared', 'jitsi', $coursemaster->shortname);
    echo "<p></p>";
}

if ($jitsi->sourcerecord != null) {
    echo "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"red\"
        class=\"bi bi-record-circle\" viewBox=\"0 0 16 16\">";
    echo "<path d=\"M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z\"/>";
    echo "<path d=\"M11 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0z\"/>";
    echo "</svg> ";
    $source = $DB->get_record('jitsi_source_record', ['id' => $jitsi->sourcerecord]);
    $author = $DB->get_record('user', ['id' => $source->userid]);
    echo addslashes(get_string('sessionisbeingrecordingby', 'jitsi', $author->firstname." ".$author->lastname));
}
echo "<p></p>";
echo get_string('minutesconnected', 'jitsi', getminutes($id, $USER->id));

if ($CFG->branch <= 311) {
    if ($jitsi->intro) {
        echo $OUTPUT->box(format_module_intro('jitsi', $jitsi, $cm->id), 'generalbox mod_introbox', 'jitsiintro');
    }
}

$fechacierre = $jitsi->timeclose;
$fechainicio = $jitsi->timeopen;

if ($jitsi->sessionwithtoken == 1) {
    $fechacierre = $jitsiinvitado->timeclose;
    $fechainicio = $jitsiinvitado->timeopen;
}

if ($today[0] < $fechacierre || $fechacierre == 0) {
    if ($today[0] > (($fechainicio)) ||
        has_capability('mod/jitsi:moderation', $context) && $today[0] > (($jitsi->timeopen) - ($jitsi->minpretime * 60))) {
        echo "<br><br>";
        $button = new moodle_url('/mod/jitsi/session.php', $urlparams);
        $options = [
            'class' => 'btn btn-primary',
            'title' => get_string('access', 'jitsi'),
        ];
        $boton = \html_writer::link($button, get_string('access', 'jitsi'), $options);
        echo $boton;
    } else {
        echo $OUTPUT->box(get_string('nostart', 'jitsi', userdate($jitsi->timeopen)));
    }
} else {
    echo $OUTPUT->box(get_string('finish', 'jitsi'));
}

echo "<br><br>";

$sql = 'select * from {jitsi_record} where jitsi = '.$jitsiid.' and deleted = 0 order by id desc';
$records = $DB->get_records_sql($sql);

$sqlusersconnected = 'select distinct userid from {logstore_standard_log} where contextid = '
    .$contextmodule->id.' and action = \'participating\'';

$usersconnected = $DB->get_records_sql($sqlusersconnected);

// Tabs.
echo "<ul class=\"nav nav-tabs\" id=\"myTab\" role=\"tablist\">";
    echo "  <li class=\"nav-item\">";
    echo "    <a class=\"nav-link active\" id=\"help-tab\" data-toggle=\"tab\" href=\"#help\"
     role=\"tab\" aria-controls=\"help\" aria-selected=\"true\">".get_string('help')."</a>";
    echo "  </li>";

if (has_capability ('mod/jitsi:viewrecords', $PAGE->context)) {
    if ($records && isallvisible($records) || has_capability ('mod/jitsi:record', $PAGE->context) && $records ||
    $CFG->jitsi_streamingoption == 1) {
        echo "  <li class=\"nav-item\">";
        echo "    <a class=\"nav-link\" id=\"record-tab\" data-toggle=\"tab\" href=\"#record\"
          role=\"tab\" aria-controls=\"record\" aria-selected=\"false\">".get_string('records', 'jitsi')."</a>";
        echo "  </li>";
    }
}

if ($usersconnected && has_capability('mod/jitsi:viewusersonsession', $PAGE->context)) {
    echo "  <li class=\"nav-item\">";
    echo "    <a class=\"nav-link\" id=\"attendees-tab\" data-toggle=\"tab\" href=\"#attendees\"
     role=\"tab\" aria-controls=\"attendees\" aria-selected=\"false\">".get_string('attendeesreport', 'jitsi')."</a>";
    echo "  </li>";
}
echo "</ul>";

// Tabs content.
echo "<div class=\"tab-content\" id=\"myTabContent\">";
if ($CFG->jitsi_help != null) {
    echo "  <div class=\"tab-pane fade show active\" id=\"help\" role=\"tabpanel\" aria-labelledby=\"help-tab\">";
    echo "  <br>";
    echo $CFG->jitsi_help;
    echo "  </div>";

    echo "  <div class=\"tab-pane fade show \" id=\"record\" role=\"tabpanel\" aria-labelledby=\"record-tab\">";
} else {
    echo "  <div class=\"tab-pane fade show active\" id=\"help\" role=\"tabpanel\" aria-labelledby=\"help-tab\">";
    echo "  <br>";
    echo $OUTPUT->box(get_string('instruction', 'jitsi'));
    echo "  </div>";

    echo "  <div class=\"tab-pane fade show \" id=\"record\" role=\"tabpanel\" aria-labelledby=\"record-tab\">";
}

if (has_capability ('mod/jitsi:viewrecords', $PAGE->context)) {
    if ($records && isallvisible($records) || has_capability ('mod/jitsi:record', $PAGE->context) && $records ||
    $CFG->jitsi_streamingoption == 1) {
        if ($records && isallvisible($records) || has_capability ('mod/jitsi:record', $PAGE->context) && $records) {
            echo "<br>";
            echo "<div class=\"row\">";
            foreach ($records as $record) {
                $sourcerecord = $DB->get_record('jitsi_source_record', ['id' => $record->source]);
                if ($sourcerecord->embed != 1) {
                    $account = $DB->get_record('jitsi_record_account', ['id' => $sourcerecord->account]);
                    if ($account->clientaccesstoken != null && $sourcerecord->timecreated != 0) {
                        if ($sourcerecord->embed == 0) {
                            doembedable($sourcerecord->link);
                        }
                    }
                }
            }
            foreach ($records as $record) {
                // Para borrar grabaciones.
                $deleteurl = new moodle_url('/mod/jitsi/view.php?id='.$cm->id.'&deletejitsirecordid=' .
                        $record->id . '&sesskey=' . sesskey() . '#record');
                $deleteicon = new pix_icon('t/delete', get_string('delete'));
                $deleteaction = $OUTPUT->action_icon($deleteurl, $deleteicon,
                    new confirm_action(get_string('confirmdeleterecordinactivity', 'jitsi')));

                $hideurl = new moodle_url('/mod/jitsi/view.php?id='.$cm->id.'&hidejitsirecordid=' .
                        $record->id . '&sesskey=' . sesskey(). '#record');
                $showurl = new moodle_url('/mod/jitsi/view.php?id='.$cm->id.'&showjitsirecordid=' .
                        $record->id . '&sesskey=' . sesskey(). '#record');
                $hideicon = new pix_icon('t/hide', get_string('hide'));
                $showicon = new pix_icon('t/show', get_string('show'));
                $hideaction = $OUTPUT->action_icon($hideurl, $hideicon, new confirm_action('Hide?'));
                $showaction = $OUTPUT->action_icon($showurl, $showicon, new confirm_action('Show?'));

                $sourcerecord = $DB->get_record('jitsi_source_record', ['id' => $record->source]);
                $context = context_module::instance($cm->id);
                if ($sourcerecord->link != null) {
                    if ($record->visible != 0 || (has_capability('mod/jitsi:record', $context)
                        && has_capability('mod/jitsi:hide', $context))) {

                        echo "<div class=\"col-sm-6\">";
                        echo "<div class=\"card\" >";
                        echo "<div class=\"card-body\">";
                        if ($record->visible == 0) {
                            echo "<h5 class=\"card-title text-muted\">";
                        } else {
                            echo "<h5 class=\"card-title\">";
                        }
                        if (has_capability('mod/jitsi:editrecordname', $context) && $jitsi->sessionwithtoken == 0) {
                            $tmpl = new \core\output\inplace_editable('mod_jitsi', 'recordname', $record->id,
                                has_capability('mod/jitsi:editrecordname', $context),
                                format_string($record->name), $record->name, get_string('editrecordname', 'jitsi'),
                                get_string('newvaluefor', 'jitsi') . format_string($record->name));
                            echo $OUTPUT->render($tmpl);
                        } else {
                            echo $record->name;
                        }
                        echo "</h5>";
                        if ($sourcerecord) {
                            echo "<h6 class=\"card-subtitle mb-2 text-muted\">".userdate($sourcerecord->timecreated)."</h6>";
                        } else {
                            echo "<h6 class=\"card-subtitle mb-2 text-muted\">".get_string('error')."</h6>";
                        }
                        echo "<div class=\"embed-responsive embed-responsive-16by9\">";
                        if ($sourcerecord && $sourcerecord->link != null) {

                            echo "<iframe class=\"embed-responsive-item\" src=\"https://youtube.com/embed/".$sourcerecord->link."\"
                                allowfullscreen></iframe>";
                        } else {
                            echo "<iframe class=\"embed-responsive-item\" src=\"https://youtube.com/embed/\"
                                allowfullscreen></iframe>";
                        }

                        echo "</div>";
                        echo "<div class=\"row\">";
                        echo "<div class=\"col-sm\">";
                        echo "</div>";
                        echo "  <div class=\"col-sm\">";
                        if ($jitsi->sessionwithtoken == 0) {
                            if (has_capability('mod/jitsi:deleterecord', $context) && !has_capability('mod/jitsi:hide', $context)) {
                                echo "<span class=\"align-middle text-right\"><p>".$deleteaction."</p></span>";
                            }
                            if (has_capability('mod/jitsi:hide', $context) && !has_capability('mod/jitsi:deleterecord', $context)) {
                                if ($record->visible != 0) {
                                    echo "<span class=\"align-middle text-right\"><p>".$hideaction."</p></span>";
                                } else {
                                    echo "<span class=\"align-middle text-right\"><p>".$showaction."</p></span>";
                                }
                            }
                            if (has_capability('mod/jitsi:hide', $context) && has_capability('mod/jitsi:deleterecord', $context)) {
                                if ($record->visible != 0) {
                                    echo "<span class=\"align-middle text-right\"><p>".$deleteaction."</span>";
                                    echo "<span class=\"align-middle text-right\">".$hideaction."</p></span>";
                                } else {
                                    echo "<span class=\"align-middle text-right\"><p>".$deleteaction."</span>";
                                    echo "<span class=\"align-middle text-right\">".$showaction."</p></span>";
                                }
                            }
                        }
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }
                }
            }
            echo "</div>";
        } else {
            echo "<br>";
            echo "<div class=\"alert alert-info\" role=\"alert\">";
            echo get_string('norecords', 'jitsi');
            echo "</div>";
        }
    }
}

echo "  </div>";
echo "  <div class=\"tab-pane fade\" id=\"attendees\" role=\"tabpanel\" aria-labelledby=\"attendees-tab\">";
echo "<br>";

$table = new html_table();
$table->head = [get_string('name'), get_string('minutes')];
$table->data = [];
foreach ($usersconnected as $userconnected) {
    if ($userconnected->userid != 0) {
        $user = $DB->get_record('user', ['id' => $userconnected->userid]);
        $table->data[] = [fullname($user), getminutes($id, $user->id)];
    }
}
echo html_writer::table($table);
echo "  </div>";
echo "<hr>";
echo $OUTPUT->footer();
