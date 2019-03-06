<?php

if (strpos($module->getUrl("EDT.php"),'redcap-dev.stanford.edu') !== false) {
    $main_pid = 14384;
    $first_event = 87428;
    $diary_form = 'diary_entry';
    $ae_form = 'adverse_event';
    $diary_event_id = 87657;
    $ae_event_id = 87428;
    $clinic_visit_form = 'clinic_visit';
    $survey_date_field = 'survey_date';
} else if (strpos($module->getUrl("EDT.php"),'redcap.stanford.edu') !== false) {
    $main_pid = 14384;
    $first_event = 87428;
    $diary_form = 'diary_entry';
    $ae_form = 'adverse_event';
    $diary_event_id = 98686;
    $ae_event_id = 87428;
    $clinic_visit_form = 'clinic_visit';
    $survey_date_field = 'survey_date';
} else if (strpos($module->getUrl("EDT.php"),'localhost') !== false) {
    $main_pid = 41;
    $first_event = 100;
    $diary_form = 'multi_oit_diary';
    $ae_form = 'adverse_event';
    $diary_event_id = 110;
    $ae_event_id = 100;
    $clinic_visit_form = 'clinic_visit';
    $survey_date_field = 'survey_date';
}

function getHeader($pid, $record_id) {

    global $module, $main_pid, $first_event;

    $primary_pk = "participant_id";
    $fields = array("$primary_pk");

    $header_data = REDCap::getData($main_pid, 'array', $record_id, $fields, array($first_event));

    $title = $header_data[$record_id][$first_event][$primary_pk];

    return $title;
}
