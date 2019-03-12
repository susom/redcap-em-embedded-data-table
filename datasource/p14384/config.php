<?php

$diary_configs = array();
if (strpos($module->getUrl("EDT.php"), 'redcap-dev.stanford.edu') !== false) {

    $diary_configs = array(
        "MAIN_PID" => 14384,
        "FIRST_EVENT" => 87428,
        "DIARY_EVENT" => 87668,
        "AE_EVENT" => 87428,
        "DIARY_FORM" => 'diary_entry',
        "AE_FORM" => 'adverse_event',
        "CV_FORM" => 'clinic_visit',
        "SURVEY_DATE" => 'rsp_survey_date'
    );

} else if (strpos($module->getUrl("EDT.php"), 'redcap.stanford.edu') !== false) {

    $diary_configs = array(
        "MAIN_PID" => 14384,
        "FIRST_EVENT" => 87428,
        "DIARY_EVENT" => 98686,
        "AE_EVENT" => 87428,
        "DIARY_FORM" => 'diary_entry',
        "AE_FORM" => 'adverse_event',
        "CV_FORM" => 'clinic_visit',
        "SURVEY_DATE" => 'survey_date'
    );

} else if (strpos($module->getUrl("EDT.php"), 'localhost') !== false) {
    $diary_configs = array(
        "MAIN_PID" => 43,
        "FIRST_EVENT" => 122,
        "DIARY_EVENT" => 132,
        "AE_EVENT" => 122,
        "DIARY_FORM" => 'diary_entry',
        "AE_FORM" => 'adverse_event',
        "CV_FORM" => 'clinic_visit',
        "SURVEY_DATE" => 'rsp_survey_date'
    );
}

