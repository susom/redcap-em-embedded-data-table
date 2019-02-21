<?php
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

use \REDCap;

require_once($module->getModulePath() . "classes/Utilities.php");

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
DEFINE(PROJECT_PID, $pid);
$user = USERID;

if (!empty($pid)) {
    $selectedProj = null;
}

$action = isset($_POST['action']) && !empty($_POST['action']) ? $_POST['action'] : null;
$module->emLog("This is the post action: " . $action);

if ($action == 'get_projects') {
    $project_id = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;

    $project_list = getProjects($project_id);

    print $project_list;
    return;
} else if ($action == 'get_displaytype') {
    $selected_project = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $arm = isset($_POST['arm']) && !empty($_POST['arm']) ? $_POST['arm'] : null;

    if (empty($selectedProj) || $selectedProj->project_id !== $selected_project) {
        $selectedProj = getProjDataDictionary($selected_project);
    }

    $dataTypes = getDisplayTypeList($selectedProj, $arm);

    print $dataTypes;
    return;

} else if ($action == 'get_keys') {
    $selected_project = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $arm = isset($_POST['arm']) && !empty($_POST['arm']) ? $_POST['arm'] : null;
    $event = isset($_POST['event']) && !empty($_POST['event']) ? $_POST['event'] : null;
    $key_event = isset($_POST['key_event']) && !empty($_POST['key_event']) ? $_POST['key_event'] : null;
    $key_form = isset($_POST['key_form']) && !empty($_POST['key_form']) ? $_POST['key_form'] : null;
    $key_field = isset($_POST['key_field']) && !empty($_POST['key_field']) ? $_POST['key_field'] : null;

    if (empty($selectedProj) || $selectedProj->project_id !== $selected_project) {
        $selectedProj = getProjDataDictionary($selected_project);
    }

    $keys = getAvailableKeysInDataProject($selectedProj, $arm, $event, $key_event, $key_form, $key_field);

    print $keys;
    return;

} else if ($action == 'get_fields') {
    $selected_project = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $form = isset($_POST['form']) && !empty($_POST['form']) ? $_POST['form'] : null;

    if (empty($selectedProj) || $selectedProj->project_id !== $selected_project) {
        $selectedProj = getProjDataDictionary($selected_project);
    }
    $result = getAvailableFields($selectedProj, $form);

    print $result;
    return;

} else if ($action == 'save_setup') {
    $selected_project = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $arm = isset($_POST['arm']) && !empty($_POST['arm']) ? $_POST['arm'] : null;
    $event = isset($_POST['event']) && !empty($_POST['event']) ? $_POST['event'] : null;
    $type = isset($_POST['type']) && !empty($_POST['type']) ? $_POST['type'] : null;
    $form = isset($_POST['form']) && !empty($_POST['form']) ? $_POST['form'] : null;
    $fields = isset($_POST['fields']) && !empty($_POST['fields']) ? $_POST['fields'] : null;
    $type_value = isset($_POST['type_value']) && !empty($_POST['type_value']) ? $_POST['type_value'] : null;
    $key_form = isset($_POST['key_form']) && !empty($_POST['key_form']) ? $_POST['key_form'] : null;
    $key_field = isset($_POST['key_field']) && !empty($_POST['key_field']) ? $_POST['key_field'] : null;
    $key_event = isset($_POST['key_event']) && !empty($_POST['key_event']) ? $_POST['key_event'] : null;
    $table_name = isset($_POST['table_name']) && !empty($_POST['table_name']) ? $_POST['table_name'] : null;

    if (empty($selectedProj) || $selectedProj->project_id !== $selected_project) {
        $selectedProj = getProjDataDictionary($selected_project);
    }

    // The record ID field needs to be inserted unless it was already selected.  First find out what it is
    // and then check to see if it was already added.  If not, add it first. This only pertains to projects
    // that are linking to other project.  Otherwise, we already know the record_id
    $project_field_names = array();
    if ($selectedProj->project_id != $pid) {
        $intersection = array_intersect($fields, array($selectedProj->table_pk_label));
        if (empty($intersection)) {
            $project_field_names[$selectedProj->table_pk] = $selectedProj->table_pk_label;
        }
    }

    // Convert the fields to the names instead of descriptions which we used to show the user
    foreach($fields as $fieldDesc=> $fieldName) {
        foreach ($selectedProj->forms[$form]["fields"] as $fieldMetaDataName => $fieldMetaDataLabel) {
            if ($fieldName == $fieldMetaDataLabel) {
                if (empty($project_field_names)) {
                    $project_field_names[$fieldMetaDataName] = $fieldMetaDataLabel;
                } else {
                    $project_field_names = array_merge($project_field_names, array($fieldMetaDataName => $fieldMetaDataLabel));
                }
                break;
            }
        }
    }

    // Now save the data in the external module project setting
    // Save both id and label
    $save_data = array(
        "arm"           => $arm,
        "event"         => $event,
        "type"          => $type,
        "form"          => $form,
        "project_id"    => $selected_project,
        "type_value"    => $type_value,
        "key_event"     => $key_event,
        "key_form"      => $key_form,
        "key_field"     => $key_field,
        "fields"        => $project_field_names
    );

    list($config_names, $config_field_after, $config_info)  = getConfigs();
    $field_after = "test";

    if (empty($config_names)) {
        setConfigs(array($table_name), array($field_after), array($save_data));
    } else {

        // See if this is an existing config that we want to update
        $index = array_search($table_name, $config_names);
        if ($index === false) {
            // This is a new config so add it to the end
            $config_names = array_merge($config_names, array($table_name));
            $config_field_after = array_merge($config_field_after, array($field_after));
            $config_info = array_merge($config_info, array($save_data));
        } else {
            // Replace the existing config with this new one
            $config_field_after[$index] = $field_after;
            $config_info[$index] = $save_data;
        }
        setConfigs($config_names, $config_field_after, $config_info);
    }

    return;

} else if ($action == 'load_config') {
    $config_name = isset($_POST['config_name']) && !empty($_POST['config_name']) ? $_POST['config_name'] : null;

    list($names, $fields, $info) = getConfigs();
    for ($icount = 0; $icount < count($names); $icount++) {
        if ($names[$icount] == $config_name) {
            $this_config = $info[$icount];
            break;
        }
    }

    print json_encode($this_config);
    return;

} else if ($action == 'get_arms') {

    $project_id = isset($_POST['project_id']) && !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $arm_id = isset($_POST['arm_id']) && !empty($_POST['arm_id']) ? $_POST['arm_id'] : null;

    if (empty($selectedProj) || $selectedProj->project_id != $project_id) {
        $selectedProj = getProjDataDictionary($project_id);
    }

    $html = getArmList($selectedProj, $arm_id);

    print $html;
    return;
} else if ($action == 'delete_config') {

    $table_name = isset($_POST['table_name']) && !empty($_POST['table_name']) ? $_POST['table_name'] : null;
    if (!empty($table_name)) {

        list($config_names, $config_field_after, $config_info)  = getConfigs();

        $key = array_search($table_name, $config_names);
        unset($config_names[$key]);
        unset($config_field_after[$key]);
        unset($config_info[$key]);

        setConfigs(array_values($config_names), array_values($config_field_after), array_values($config_info));
    }

    return;
}

// This needs to be after the api checks otherwise it gets added to the return data
require APP_PATH_DOCROOT . "ProjectGeneral/header.php";

function getSavedConfigs() {
    global $module;

    list($config_names, $config_field_after, $config_info)  = getConfigs();

    $html = '<input class="form-control" name="edt_name" id="edt_name" list="config_name" onchange="getSelectedConfig()">';
    if (count($config_names) > 0) {
        $html .=  "<datalist id='config_name'>";
        foreach ($config_names as $setup => $setupInfo) {
            $html .= "<option value='" . $setupInfo . "'></option>";
        }
        $html .= "</datalist>";
    }

    return $html;
}

function getProjects($selected_project = null) {

    global $user, $pid, $module;

    // If a project ID was provided, use it otherwise use the currently selected project ID.
    if (empty($selected_project)) {
        $this_proj_id = $pid;
    } else {
        $this_proj_id = $selected_project;
    }

    // Retrieve the projects that the user has access to
    $query = "select pr.project_id, pr.app_title " .
             " from redcap_user_rights ur, redcap_projects pr " .
             " where ur.username = '" . $user . "'" .
             " and ur.project_id = pr.project_id order by pr.project_id";
    $result = db_query($query);

    // Setup up the input list with available projects defaulting to either the entered project or if that is null,
    // then the current project.
    $datalist =  "<datalist id='proj'>";
    while($row = db_fetch_array($result)) {

        //Nested array with email as key and entire row as value
        $proj_id = $row['project_id'];
        $title = $row['app_title'];
        $datalist .= "<option value='" . projectLabel($proj_id, $title) . "'></option>";
        if ($proj_id == $this_proj_id) {
            $input =  "<input class='form-control' id='selected_proj' name='selected_proj' list='proj' value='" . projectLabel($proj_id, $title) . "' onclick='getSelectedProject()' onchange='getSelectedProject()'>";
        }
    }
    $datalist .= "</datalist>";

    return $input . $datalist;
}

function projectLabel($pid, $title) {
    return "[pid=" . $pid . "] " . $title;
}

function getArmList($selectedProj, $arm_id = null) {

    global $module;

    if ($selectedProj->numArms == 1) {
        return null;
    } else {
        $input = "<input class='form-control' id='arms' name='arms' list='arm_list' onchange='getSelectedArm()'>";
        $html = "<datalist id='arm_list'>";

        foreach($selectedProj->events as $event => $eventInfo) {
            if (!empty($arm_id) && $arm_id == $eventInfo["id"]) {
                $input = "<input class='form-control' id='arms' name='arms' list='arm_list' value = '" . $eventInfo["name"] . "' onchange='getSelectedArm()'>";
            }
            $html .= "<option value='" . $eventInfo["name"] . "' data-arm='" . $eventInfo["name"] . "' data-armid='" . $eventInfo["id"] . "'></option>";
        }

        $html .= "</datalist>";
        return $input . $html;
    }
}

function retrieveEventListForArm($selectedProj, $armId)
{
    // If there are arms, we know which is selected.  See if there are repeating forms in this event or if we have repeating events
    // or if we have multiple events with the same form.
        $eventsList = array();
        if ($selectedProj->numArms > 1) {
            foreach ($selectedProj->events as $oneEvent => $oneEventInfo) {
                if ($oneEventInfo["id"] == $armId) {
                    $selectedArmId = $oneEventInfo["id"];
                    $selectedArm = $oneEventInfo["name"];
                    foreach ($oneEventInfo["events"] as $eachEvent => $eachEventInfo) {
                        $eventsList[$eachEvent] = $eachEventInfo["descrip"];
                    }
                }
            }
        } else {
            $selectedArm = $selectedProj->events[1]["name"];
            $selectedArmId = $selectedProj->events[1]["id"];
            foreach ($selectedProj->events[1]["events"] as $eventId => $eventInfo) {
                $eventsList[$eventId] = $eventInfo["descrip"];
            }
        }

        return array($eventsList, $selectedArm, $selectedArmId);
}

function getDisplayTypeList($selectedProj, $armId) {
    global $module, $pid;

    // If we have more than 1 arm and an arm is not selected, return nothing since we don't know what to do
    if ($selectedProj->numArms > 1 && (empty($armId) || $armId == '*')) {
        return null;
    }

    list($eventsList, $selectedArm, $selectedArmId) = retrieveEventListForArm($selectedProj, $armId);

    // Check for repeating forms and repeating events
    $repeatEvents = "";
    $repeatForms = "";
    if (!empty($selectedProj->RepeatingFormsEvents)) {
        foreach ($selectedProj->RepeatingFormsEvents as $eventId => $eventForms) {

            // See if this repeating form event is in our list of selected events
            if (array_key_exists($eventId, $eventsList) === true) {

                // If the forms list is WHOLE, it means all forms are repeating so add all to the list
                if ($eventForms == 'WHOLE') {
                    foreach($selectedProj->eventsForms[$eventId] as $form) {
                        $repeatEvents .= "<option value='[Arm: $selectedArm] $form - form in repeating event' data-arm='$selectedArmId' data-event='$eventId' data-form='$form' data-type='repeatingEvent'></option>";
                    }
                } else {
                    // Otherwise add each of the repeating forms
                    foreach ($eventForms as $formName => $formInfo) {
                        $formLabel = $selectedProj->forms[$formName]["menu"];
                        $repeatForms .= "<option value='[Arm: $selectedArm] $formLabel - repeating form' data-arm='$selectedArmId' data-event='$eventId' data-form='$formName' data-type='repeatingForm'></option>";
                    }
                }
            }
        }
    }

    // If the same form is used across events, include the form in the list
    $list_of_forms = array();
    foreach ($selectedProj->eventsForms as $eventId => $formList) {
        if (array_key_exists($eventId, $eventsList) === true) {
            if (empty($list_of_forms)) {
                $list_of_forms = $formList;
            } else {
                $list_of_forms = array_merge($list_of_forms, $formList);
            }
        }
    }

    // Once we have a list of all forms, see if a form is present in more than 1 event.
    $acrossEventForms = "";
    if (!empty($list_of_forms)) {
        $combined_array = array_count_values($list_of_forms);
        foreach ($combined_array as $formName => $formCount) {
            if ($formCount > 1) {
                $acrossEventForms .= "<option value='[Arm: $selectedArm] $formName - across events' data-arm='$selectedArmId' data-event='*' data-form='$formName' data-type='events'></option>";
            }
        }
    }

    // If this project is not the same as the data project, add the option to use the key (i.e. diary project)
    if ($selectedProj->project_id != $pid) {
        $key = "<option value='Common key project' data-arm='$selectedArmId' data-event='*' data-form='$formName' data-type='primary_key'></option>";
    }


    $select = "<input class='form-control' id='forms' name='forms' list='form_list' onchange='getKeyList()'>";
    $select .= "<datalist id='form_list'>";

    // Put together our dropdown list based on the forms to be included
    // If we have a common key project
    if (!empty($key)) {
        $select .= "<option value='--- Key in project ($selectedProj->project_id) linking to this project ($pid) ---' readonly></option>";
        $select .= $key;
    }

    // If we found repeating events, add the forms
    if (!empty($repeatEvents)) {
        $select .= "<option value='--- Display data in repeating events ---' readonly></option>";
        $select .= $repeatEvents;
    }

    // If we found repeating forms, add them
    if (!empty($repeatForms)) {
        $select .= "<option value='--- Display data in a repeating form ---' readonly></option>";
        $select .= $repeatForms;
    }

    // If we found forms in more than one event, add them
    if (!empty($acrossEventForms)) {
        $select .= "<option value='--- Display data forms across events ---' readonly></option>";
        $select .= $acrossEventForms;
    }

    $select .= "</datalist>";

    return $select;

}

function getAvailableKeysInDataProject($selectedProj, $arm, $event, $key_event=null, $key_form=null, $key_field=null) {
    global $pid, $module;
    $allowable_type_fields = array("text", "sql", "select");
    $allowable_validation_types = array("int", "float", "");

    list($eventsList, $selectedArm, $selectedArmId) = retrieveEventListForArm($selectedProj, $arm);

    // If the data is coming from a different project than, the user must select a foreign key in the data project which
    // corresponds to the record_id in our current project
    if ($selectedProj->project_id == $pid) {
        return null;
    } else {

        // Create dropdown for keys
        $input = "<input class='form-control' id='key' name='key' list='key_list' onchange='getFieldList()'>";
        $select = "<datalist id='key_list'>";

        // Loop over all events in this arm
        foreach($eventsList as $eventId => $eventName) {

            // Loop over all forms in this event
            foreach($selectedProj->eventsForms[$eventId] as $formNum => $formName) {

                // Check to make sure this form is not repeating and not in a repeating event
                if (empty($selectedProj->RepeatingFormsEvents[$eventId]) ||
                        (array_key_exists($formName, $selectedProj->RepeatingFormsEvents[$eventId]) === false &&
                                ($selectedProj->RepeatingFormsEvents[$eventId] != "WHOLE"))) {

                    // Loop over each field to see if we should include it
                    foreach($selectedProj->forms[$formName]["fields"] as $fieldName => $fieldInfo) {

                        // Try to narrow down the number of keys to list.
                        if (in_array($selectedProj->metadata[$fieldName]["element_type"], $allowable_type_fields) &&
                                ($selectedProj->metadata[$fieldName]["element_enum"] == "") &&
                                (in_array($selectedProj->metadata[$fieldName]["element_validation_type"], $allowable_validation_types))) {

                            //  We are only allowing text fields, select (for dropdowns) and sql fields.
                            // For validation, we are only allowing no validation or numbers. Will have to update this with stanford specific field types.
                            $formDescrip = $selectedProj->forms[$formName]["menu"];
                            if (!empty($key_field) && !empty($key_form && !empty($key_event)) &&
                                    ($formName == $key_form) && ($fieldName == $key_field) && ($eventId == $key_event)) {
                                $input = "<input class='form-control' id='key' name='key' list='key_list' value='[Event: $eventName, Form: $formDescrip] $fieldInfo' onchange='getFieldList()'>";
                            }
                            $select .= "<option value='[Event: $eventName, Form: $formDescrip] $fieldInfo' data-event='$eventId' data-form='$formName' data-type='primaryKey' data-field='$fieldName'></option>";
                        }
                    }
                }
            }
        }

        $select .= "</datalist>";
    }

    return $input . $select;
}

function getAvailableFields($selectedProj, $form) {
    global $module;
    $html = "";

    foreach ($selectedProj->forms[$form]["fields"] as $fieldName => $fieldLabel) {
        $html .= "<li>$fieldLabel</li>";
    }

    return $html;
}


?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <title>Embedded Data Table Setup</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/EDT.css") ?>" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />

        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
    </head>
    <body>

        <h3>Embedded Data Table Setup</h3>
        <div>
            This is the Setup Page to create Embedded Data Tables for your project.  Please select the data table
            that you would like to modify or create a new data table
        </div>
        <br>

        <div class="container">

            <div class="row" id="config_id" style="display: inline;" >
                <div>
                    <label class="col-form-label"><b>Embedded Table Name - select an existing setup or create a new one</b></label>
                </div>
                <div>
                    <?php echo getSavedConfigs(); ?>
                    <p id="edt_name_check"></p>
                </div>
            </div>

            <div class="row" id="projects_id">
                <div>
                    <label class="col-form-label"><b>Select the project where the data resides:</b></label>
                </div>
                <div id="project_list_id">
                </div>
            </div>

            <div class="row" id="arm_id">
                <div>
                    <label class="col-form-label"><b>There is more than one arm in this project, please select the arm you want to use:</b></label>
                </div>
                <div id="arm_list_id">
                    <p id="arms_noselection_check"></p>
                </div>
           </div>

            <div class="row" id="form_id">
                <div>
                    <label class="col-form-label"><b>Select the type of data you want to use:</b></label>
                </div>
                <div id="form_list_id">
                </div>
            </div>

            <div class="row" id="key_id">
                <div>
                    <label class="col-form-label"><b>Select the foreign key you want to use in your data project:</b></label>
                </div>
                <div id="key_list_id">
                </div>
            </div>


            <div id="fields">
                <h6 id="instructions">
                    <kbd>Click</kbd> to select individual items<br>
                    <kbd>Ctrl</kbd> + <kbd>Click</kbd> to select multiple items<br>
                </h6>

                <div class="form-group row">
                    <label class="col-sm-6 col-form-label"><b>Selectable Fields</b></label>
                    <label class="col-sm-6 col-form-label"><b>Selected Fields (in order they will appear in the display)</b><br><p id="selected_fields_check"></p></label>
                    <ul id="fields_selectable" class="col-sm-6">
                    </ul>

                    <ul id="fields_selected" class="col-sm-6">
                    </ul>
                </div>

                <div align="left">
                    <input class="button" type="submit" value="Save Setup" onclick="saveSetup()"/>
                    <input class="button" type="submit" value="Delete Setup" onclick="deleteSetup()"/>
                </div>
           </div>

        </div>  <!-- END CONTAINER -->
    </body>
</html>

<script>
    document.getElementById("fields").style.display = "none";
    document.getElementById("form_id").style.display = "none";
    document.getElementById("projects_id").style.display = "none";
    document.getElementById("arm_id").style.display = "none";
    document.getElementById("key_id").style.display = "none";

    $("ul").on('click', 'li', function (e) {

        $(this).toggleClass("selected");

        }).sortable({
            connectWith: "ul",
            delay: 150, //Needed to prevent accidental drag when trying to select
            revert: 0,
            helper: function (e, item) {
            //Basically, if you grab an unhighlighted item to drag, it will deselect (unhighlight) everything else
            if (!item.hasClass('selected')) {
                item.addClass('selected').siblings().removeClass('selected');
            }

            //////////////////////////////////////////////////////////////////////
            //HERE'S HOW TO PASS THE SELECTED ITEMS TO THE `stop()` FUNCTION:

            //Clone the selected items into an array
            var elements = item.parent().children('.selected').clone();

            //Add a property to `item` called 'multidrag` that contains the
            //  selected items, then remove the selected items from the source list
            item.data('multidrag', elements).siblings('.selected').remove();

            //Now the selected items exist in memory, attached to the `item`,
            //  so we can access them later when we get to the `stop()` callback

            //Create the helper
            var helper = $('<li/>');
            return helper.append(elements);
            },
            stop: function (e, ui) {
            //Now we access those items that we stored in `item`s data!
            var elements = ui.item.data('multidrag');

            //`elements` now contains the originally selected items from the source list (the dragged items)!!

            //Finally I insert the selected items after the `item`, then remove the `item`, since
            //  item is a duplicate of one of the selected items.
            ui.item.after(elements).remove();

        }
    });

    function getProjectID() {
        var project_title = document.getElementById("selected_proj").value;
        var project_id = "";
        if (project_title.substring(0,5) === "[pid=") {
            project_id = project_title.replace(/].*$/, '').replace("[pid=", '');
            if (isNaN(project_id)) {
                project_id = "";
            }
        }
        return project_id;
    }

    function getSelectedFormElement() {

        var selected_option = "";
        var selected_data = document.getElementById("forms").value;
        if (selected_data !== "" && selected_data.substring(0,3) !== '---') {
            selected_option = $('#form_list').find("[value='" + selected_data + "']");
        }

        return selected_option;
    }

    function getSelectedProject() {
        var project_id = getProjectID();
        if (project_id !== "") {
            edt.getArmsList(project_id, null);
        } else {
            document.getElementById("form_id").style.display = "none";
        }
    }

    function clearSelectedFields() {
        // Clear out the selected fields (if any)
        var selected_items = document.getElementById("fields_selected");
        selected_items.innerHTML = null;
    }

    function getFieldList() {

        var selected_option = getSelectedFormElement();
        if (selected_option !== "") {

            var project_id = getProjectID();
            var arm = selected_option.data("arm");
            var event = selected_option.data("event");
            var type = selected_option.data("type");
            var form = selected_option.data("form");

            // Check to see if option of a primary key filter is selected and if so, we need to prompt the
            // user for the filter key
            if (type === 'primaryKey') {
                var primaryKey = selected_option.data("field");
            }

            // Go retrieve field names
            clearSelectedFields();
            edt.getFieldNames(project_id, form);

        } else {
            clearSelectedFields();
            document.getElementById("forms").value = "";
            document.getElementById("fields").style.display = "none";
        }
    }

    function getSelectedKey() {
        var key = document.getElementById("key");
        if (key == null) {
            return null;
        } else {
            var selected_data = document.getElementById("key").value;
            var selected_option = $('#key_list').find("[value='" + selected_data + "']");
        }
        return selected_option;
    }

    function getKeyList() {
        var selected_option = getSelectedFormElement();
        if (selected_option !== "") {

            var project_id = getProjectID();
            var arm = selected_option.data("arm");
            var event = selected_option.data("event");
            edt.getKeyList(project_id, arm, event, null, null, null);
        }
    }

    function getSelectedArm() {

        var arm_name = document.getElementById("arms").value;
        if (arm_name === "") {
            document.getElementById("arms_noselection_check").innerHTML = "* Required value";
        } else {
            var noselection = document.getElementById("arms_noselection_check");
            if (noselection) {
                noselection.innerHTML = "";
            }
            var selected_option = $("#arm_list").find('option[value="' + arm_name + '"]');
            var arm_id = selected_option.data("armid");
            edt.getDisplayList(getProjectID(), arm_id);
        }
    }

    function getSelectedConfig() {
        var config_name = document.getElementById("edt_name").value;
        var found = $("#config_name").find("option[value='" + config_name + "']");
        document.getElementById("edt_name_check").innerHTML = null;

        // If the user selected an existing config, preset the values from what was selected previously
        if (found != null && found.length > 0) {
            edt.loadConfig(config_name);
        } else if (config_name === "" ) {
            document.getElementById("edt_name_check").innerHTML = "* Required value";
        } else {

            // if the project list is not empty, we already retrieved the list of projects this user
            // has access to so they are probably just changing the table name so don't go out to
            // retrieve the list again
            var exists = document.getElementById("proj");
            if (exists === null) {
                edt.getProjects();
            }
        }
    }

    function getSelectedFields() {

        var ncnt;
        var field_list = [];

        var list = document.getElementById("fields_selected").getElementsByTagName("li");
        for (ncnt=0; ncnt < list.length; ncnt++) {
            field_list.push(list[ncnt].textContent);
        }

        return field_list;
    }

    function deleteSetup() {
        // Retrieve this config name
        var table_name = document.getElementById("edt_name").value;
        if (table_name !== "") {
            edt.deleteConfig(table_name);
        }
    }

    function saveSetup() {

        var submit = 1;

        // Retrieve selected fields. If there is not at least one field selected, don't save
        var field_array = getSelectedFields();
        if (field_array.length === 0) {
            document.getElementById("selected_fields_check").innerHTML = "* Must select at least one field";
            submit = 0;
        }

        var key_form = null;
        var key_field = null;
        var key_event = null;
        var selected_key = getSelectedKey();
        if (selected_key != null) {
            key_form = selected_key.data("form");
            key_field = selected_key.data("field");
            key_event = selected_key.data("event");
       }

        var project_id = getProjectID();
        var selected_form = getSelectedFormElement();
        if (selected_form !== "") {
            var arm = selected_form.data("arm");
            var event = selected_form.data("event");
            var type = selected_form.data("type");
            var form = selected_form.data("form");
            var type_value = document.getElementById("forms").value
        } else {
            submit = 0;
        }

        // Retrieve this config name
        var table_name = document.getElementById("edt_name").value;
        if (table_name === "") {
            submit = 0;
        }

        if (submit > 0) {
            edt.saveConfig(table_name, project_id, arm, event, type, field_array, form, type_value, key_event, key_form, key_field);
        }
    }

    var edt = edt || {};

    edt.getArmsList = function (project_id, arm_id) {

        // Make the API call to see if there are multiple arms in this project
        $.ajax({
            type: "POST",
            datatype: "html",
            async: false,
            data: {
                "action"     : "get_arms",
                "project_id" : project_id,
                "arm_id"     : arm_id
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in get_arms request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (html) {
            if (html === "") {
                document.getElementById("arm_id").style.display = "none";
                edt.getDisplayList(project_id, '*');
            } else {
                document.getElementById("arm_id").style.display = "inline";
                document.getElementById("arm_list_id").innerHTML = html;
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed to retrieve list of arms in getArmsList");
        });

    };

    edt.getProjects= function (project_id) {

        // Make the API call to retrieve the type of display we are creating
        $.ajax({
            type: "POST",
            datatype: "html",
            async: false,
            data: {
                "action"     : "get_projects",
                "project_id" : project_id
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in get_fields request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (html) {
            if (html === "") {
                document.getElementById("projects_id").style.display = "none";
            } else {
                document.getElementById("project_list_id").innerHTML = html;
                document.getElementById("projects_id").style.display = "inline";
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed to retrieve list of projects in getProjects");
        });
    };

    edt.getKeyList= function (project_id, arm, event, key_event, key_form, key_field) {

        // Make the API call to retrieve the type of display we are creating
        $.ajax({
            type: "POST",
            datatype: "html",
            async: false,
            data: {
                "action"     : "get_keys",
                "project_id" : project_id,
                "arm"        : arm,
                "event"      : event,
                "key_event"  : key_event,
                "key_form"   : key_form,
                "key_field"  : key_field
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in get_fields request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (html) {
            if (html === "") {
                document.getElementById("key_id").style.display = "none";
                // No keys so the data is in the same project as the config
                getFieldList();
            } else {
                document.getElementById("key_list_id").innerHTML = html;
                document.getElementById("key_id").style.display = "inline";
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed to retrieve list of projects in getProjects");
        });
    };


    edt.getFieldNames = function (project_id, form) {

        // Make the API call to retrieve list of fields that are selectable
        $.ajax({
            type: "POST",
            datatype: "html",
            async: false,
            data: {
                "action"     : "get_fields",
                "project_id" : project_id,
                "form"       : form
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in get_fields request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (html) {
            if (html === "") {
                document.getElementById("fields").style.display = "none";
            } else {
                document.getElementById("fields_selectable").innerHTML = html;
                document.getElementById("fields").style.display = "inline";
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed in getFieldNames for instrument " + instrument + " and event " + event);
        });
    };

    edt.getDisplayList = function (project_id, arm) {

        // Make the API call to retrieve list of fields that are selectable
        $.ajax({
            type: "POST",
            datatype: "html",
            async: false,
            data: {
                "action"     : "get_displaytype",
                "project_id" : project_id,
                "arm"        : arm
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in set_projectID request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (html) {
            if (html === "") {
                document.getElementById("form_id").style.display = "none";
            } else {
                document.getElementById("form_id").style.display = "inline";
                document.getElementById("form_list_id").innerHTML = html;
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed in set_projectID for project_id " + project_id);
        });
    };

    edt.saveConfig = function (table_name, project_id, arm, event, type, field_array, form, type_value, key_event, key_form, key_field) {

        // Make the API call to save this setup for later display
        $.ajax({
            type: "POST",
            datatype: "html",
            async: true,
            data: {
                "action"     : "save_setup",
                "table_name" : table_name,
                "project_id" : project_id,
                "arm"        : arm,
                "event"      : event,
                "type"       : type,
                "form"       : form,
                "fields"     : field_array,
                "key_event"  : key_event,
                "key_form"   : key_form,
                "key_field"  : key_field,
                "type_value" : type_value
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in set_projectID request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (url) {
            alert("Your configuration " + table_name + " has been successfully saved!");
            location.reload();

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed in set_projectID for project_id " + project_id);
        });
    };

    edt.deleteConfig = function (table_name) {

        // Make the API call to save this setup for later display
        $.ajax({
            type: "POST",
            datatype: "html",
            async: true,
            data: {
                "action"     : "delete_config",
                "table_name" : table_name
            },
            success:function(html) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in set_projectID request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (url) {
            alert("Your configuration " + table_name + " has been successfully deleted!");
            location.reload();

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed in set_projectID for project_id " + project_id);
        });
    };

    edt.loadConfig = function (config_name) {

        // Make the API call to load the config
        $.ajax({
            type: "POST",
            datatype: "text",
            async: true,
            data: {
                "action"     : 'load_config',
                "config_name" : config_name
            },
            success:function(data) {
            },
            error:function(jqXhr, textStatus, errorThrown) {
                console.log("Error in set_projectID request: ", jqXHR, textStatus, errorThrown);
            }

        }).done(function (data) {

            // Only preload data if we have a previously saved config
            var data_array = JSON.parse(data);
            var project_id = data_array.project_id;
            if (project_id != null) {

                // Retrieve list of projects this user has access to
                edt.getProjects(project_id);

                // Get list of arms and pre-select the saved arm
                edt.getArmsList(project_id, data_array.arm);

                // Set selected table types and select the one stored in config
                edt.getDisplayList(project_id, data_array.arm);
                document.getElementById("forms").value = data_array.type_value;

                // See if there is a key value. If so, set it
                if (data_array.key_field !== "") {
                    edt.getKeyList(project_id, data_array.arm, data_array.event, data_array.key_event, data_array.key_form, data_array.key_field);
                }

                // Make an array out of the json field object
                var fields = [];
                var keys = Object.keys(data_array.fields);
                keys.forEach(function(key) {
                    fields.push(data_array.fields[key]);
                });

                // Set selected fields in the selected list and take them out of the selectable list
                edt.getFieldNames(project_id, data_array.form);
                for (var n = 0; n < fields.length; n++) {
                    var field_list = Array.from(document.querySelectorAll('#fields_selectable>li'));
                    var field_nodes = document.getElementById('fields_selectable');
                    for (var i = 0; i < field_list.length; i++) {
                        if (field_list[i].textContent === fields[n]) {
                            $('#fields_selected').append('<li>' + field_list[i].textContent + '</li>');
                            field_nodes.removeChild(field_nodes.childNodes[i]);
                            break;
                        }
                    }
                }
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Failed in set_projectID for project_id " + project_id);
        });

    };

</script>