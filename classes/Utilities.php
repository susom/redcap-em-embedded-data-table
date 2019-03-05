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

function getLabel($selectedProj, $field, $value)
{
    global $module;

    if (empty($field)) {
        $module->emError("The variable list is undefined so cannot retrieve data dictionary options.");
    }

    $fieldInfo = $selectedProj->metadata[$field];

    $label = null;
    switch ($fieldInfo["element_type"]) {
        case "select":
        case "radio":
        case "yesno":

            $optionList = $fieldInfo["element_enum"];
            $options = explode('\n', $optionList);
            foreach ($options as $optionKey => $optionValue) {

                $option = explode(',', $optionValue, 2);
                if (trim($option[0]) == $value) {
                    if (empty($label)) {
                        $label = trim($option[1]);
                    } else {
                        $label .= ', ' . trim($option[1]);
                    }
                }
            }

        break;
        case "checkbox":

            $optionList = $fieldInfo["element_enum"];
            $options = explode('\n', $optionList);
            foreach ($options as $optionKey => $optionValue) {
                $option = explode(',', $optionValue);
                if ($value[trim($option[0])] == 1) {
                    if (empty($label)) {
                        $label = trim($option[1]);
                    } else {
                        $label .= ', ' . trim($option[1]);
                    }
                }
            }
            break;
        default:
            $label = $value;
    }

    return $label;
}
