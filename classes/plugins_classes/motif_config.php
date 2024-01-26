<?php
namespace Stanford\EDT;

use \REDCap;

// Setup configuration for the scripts since they all need this data
$diary_configs = array(
        "FIRST_EVENT_NAME" => 'screening_arm_1',
        "DIARY_EVENT_NAME" => 'diary_arm_1',
        "AE_EVENT_NAME" => 'screening_arm_1',
        "DIARY_FORM" => 'diary_entry',
        "AE_FORM" => 'aes_and_fcrs',
        "CV_FORM" => 'clinic_visit',
        "SURVEY_DATE" => 'rsp_survey_date'
);

// Convert event names to event numbers
$diary_configs["FIRST_EVENT"] = REDCap::getEventIdFromUniqueEvent($diary_configs["FIRST_EVENT_NAME"]);
$diary_configs["DIARY_EVENT"] = REDCap::getEventIdFromUniqueEvent($diary_configs["DIARY_EVENT_NAME"]);
$diary_configs["AE_EVENT"] = REDCap::getEventIdFromUniqueEvent($diary_configs["AE_EVENT_NAME"]);

// These are Diary Symptoms used to track AEs
$symptom_aliases_diary = array(
    1 => 'edemaface', 2 => 'itchym', 3 => 'itchysk', 4 => 'abpain',
    5 => 'nascon', 6 => 'diarrh', 7 => 'tbreath', 8 => 'tgthroat',
    9 => 'cough', 10 => 'vomit', 11 => 'urtica', 12 => 'hoarse',
    13 => 'rash', 14 => 'lighthd', 15 => 'itchyeye', 16 => 'nausea',
    17 => 'orpain', 18 => 'nasitch', 19 => 'runnose',
    99 => 'othersx');
$ae_terms = array(
    1=>804, 2=>7022, 3=>2320, 4=>702,
    5=>2229, 6=>723, 7=>"", 8=>2264,
    9=>2213, 10=>7017, 11=>2333, 12=> 2217,
    13=>2340, 14=>1747, 15=>603, 16=>779,
    17=>784, 18=>2265, 19=>2263,
    99=>"");
