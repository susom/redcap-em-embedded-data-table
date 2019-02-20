<?php
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 2019-02-19
 * Time: 09:48
 */
use \Project;

function getProjDataDictionary($selected_pid) {
    global $module;

    $selectedProj = null;
    if (!empty($selected_pid)) {
        try {
            $selectedProj = new Project($selected_pid);
            if ($selectedProj->project_id == $selected_pid) {
                $selectedProj->setRepeatingFormsEvents();
            } else {
                $module->emError("Error retrieving project data dictionary for project $selected_pid");
                $selectedProj = null;
            }
        } catch (Exception $exception) {
            $module->emError("Exception thrown when retrieving Project Data Dictionary for project $selected_pid");
        }
    }
    return $selectedProj;
}

function getConfigs() {
    global $module;
    $config_names = $module->getProjectSetting("config_name");
    $config_field = $module->getProjectSetting("config_field_after");
    $config_info = $module->getProjectSetting("config_info");
    return array($config_names, $config_field, $config_info);
}

function setConfigs($config_names, $config_fields, $config_info) {
    global $module;
    $module->setProjectSetting("config_name", $config_names);
    $module->setProjectSetting("config_field_after", $config_fields);
    $module->setProjectSetting("config_info", $config_info);
}
