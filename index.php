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
 * Course Completeion Status Report.
 *
 * @package   report_completionstatus
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

//Set Student ID Parameter.
$student_id = optional_param('id', 0, PARAM_INT);

//Set Paging Parameters where pages = pg and show page after each progress.
$pg = optional_param('page', 0, PARAM_INT); 
$pg2 = optional_param('page2', 0, PARAM_INT); 
$perpg = optional_param('perpage', 10, PARAM_INT); 
$base_url = new moodle_url('index.php', array('id' => $student_id));

admin_externalpage_setup('reportcompletionstatus', '', null, '', array('pagelayout' => 'report'));
require_login();

// Check Capabilities and set context.
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/coursecompletionstatus:view', $context);

//Get student data and populate table
$get_students = "SELECT firstname, lastname, id, email
                 FROM {user}
                 ORDER BY id";
$student_courseCount = $DB->get_records_sql($get_students);
$student_count = count($student_courseCount);
$all_students = $DB->get_records_sql($get_students, array('1', '0'), $pg * $perpg, $perpg);

//Set students for select student drop down
$table = 'user';
$conditions = array('confirmed' => '1');
$sort = 'firstname';
$fields = 'id, firstname, lastname';
$result = $DB->get_records_menu($table, $conditions, $sort, $fields);

//start table output
echo $OUTPUT->header();

echo html_writer::start_tag('div', array('class' => 'span9 well'));
echo html_writer::start_tag('p');
echo ($student_count.' '.get_string('nostudents', 'report_completionstatus'));
echo html_writer::end_tag('p');

echo html_writer::end_tag('div'); // End span 9 well.
echo html_writer::start_tag('div', array('class' => 'span9 well'));
echo html_writer::start_tag('p');
echo (get_string('coursereport', 'report_completionstatus') .' - ');
echo ($student_count.' '.get_string('nostudents', 'report_completionstatus'));
echo html_writer::end_tag('p');

// Show all users.
$table = new html_table();
$table->width = '*';
$table->align = array('left', 'left', 'left', 'left', 'left', 'left');
$table->head = array(get_string('student_th1', 'report_completionstatus'),
get_string('student_th2', 'report_completionstatus'));

//iterate over students and fill in data
foreach ($all_students as $row) {
    $student_fullname = $row->firstname ." ".  $row->lastname;
    $student_data = array();
    $student_data[] = $student_fullname;
    $student_data[] = $row->email;
    $table->data[] = $student_data;
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($student_count, $pg, $perpg, $base_url, $pagevar = 'page2');

echo html_writer::end_tag('div');

//user select form
echo html_writer::start_tag('form', array('action' => 'index.php', 'method' => 'post'));
echo html_writer::start_tag('div');
$table = new html_table();
$table->width = '*';
$table->align = array('left', 'left', 'left', 'left', 'left', 'left');

$usermenu = html_writer::label('Select Student', 'menureport', false, array('class' => 'accesshide'));
$usermenu .= html_writer::select($result, 'id', $result);

$table->data[] = array(get_string('selectuser', 'report_completionstatus'), $usermenu,
                       html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('view'))));

echo html_writer::table($table);
echo html_writer::end_tag('div');
echo html_writer::end_tag('form');

//Show report only when a student is selected
if (!empty($result) && $student_id != 0) {
    $course_data = "SELECT u.id AS userid, c.id as courseid,
                    c.fullname AS fullname,
                    u.firstname AS firstname,
                    u.lastname AS lastname,
                    u.email,
                    cc.timecompleted,
                    cc.timestarted,
                    gg.finalgrade
                    FROM {user} u
                    INNER JOIN {role_assignments} ra ON ra.userid = u.id
                    INNER JOIN {context} ct ON ct.id = ra.contextid
                    INNER JOIN {course} c ON c.id = ct.instanceid
                    AND c.enablecompletion = '1' AND u.id = ".$student_id."
                    INNER JOIN mdl_role r ON r.id = ra.roleid and r.id = 5
                    LEFT OUTER JOIN {course_completions} cc ON (cc.course = c.id) AND cc.userid = u.id
                    LEFT JOIN
                    (SELECT u.id AS userid,c.id as courseid, g.finalgrade AS finalgrade
                    FROM {user} u
                    JOIN {grade_grades} g ON g.userid = u.id
                    JOIN {grade_items} gi ON g.itemid =  gi.id
                    JOIN {course} c ON c.id = gi.courseid where gi.itemtype = 'course') gg
                    ON gg.userid = u.id and gg.courseid = c.id
                    ORDER BY u.lastname";


    $course_dataCount = $DB->get_records_sql($course_data);
    $course_studentCount = count($course_dataCount);
    $course_reportData = $DB->get_records_sql($course_data, array(), $pg2 * $perpg, $perpg);

    //output report table
    $table = new html_table();
        $table->width = '*';
        $table->align = array('left', 'left', 'left', 'left', 'left', 'left');
        $table->head  = array(get_string('course_th1', 'report_completionstatus'),
                            get_string('course_th2', 'report_completionstatus'),
                            get_string('course_th3', 'report_completionstatus'));

    //iterate over data and output result to table
    foreach ($course_reportData as $row) {
        $rep = array();
        $rep[] = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$row->courseid.'">'.$row->fullname.'</a>';
        $rep[] = ($row->timecompleted ? 'Completed' : 'Not Complete');
        $rep[] = ($row->timecompleted ? date('jS F Y H:i:s', ($row->timecompleted)) : 'Not completed');
        $table->data[] = $rep;
    }

    /**
     * Show course data 
     * handle empty data set to inform admin
     */
    if (!empty($course_reportData)) {
        echo $OUTPUT->heading($row->firstname ." ". $row->lastname);
    } else {
        echo $OUTPUT->heading($result[$student_id]);
        echo (get_string('noenrolments', 'report_completionstatus'). '<br /><br />');
    }

    //iutput report table
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($course_studentCount, $pg2, $perpg, $base_url, $pagevar = 'page2');
}
echo $OUTPUT->footer();