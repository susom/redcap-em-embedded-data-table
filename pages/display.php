<?php
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

use \REDCap;
use \Project;

require_once ($module->getModulePath() . "classes/CreateDisplay.php");
require_once ($module->getModulePath() . "classes/RepeatingFormsExt.php");
require_once ($module->getModulePath() . "classes/Utilities.php");

// This needs to be after the api checks otherwise it gets added to the return data
require APP_PATH_DOCROOT . "ProjectGeneral/header.php";

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$record_id = isset($_GET['record']) && !empty($_GET['record']) ? $_GET['record'] : null;
$displays = isset($_GET['displays']) && !empty($_GET['displays']) ? $_GET['displays'] : null;
$title = isset($_GET['title']) && !empty($_GET['title']) ? $_GET['title'] : null;

$module->emDebug("In display: pid $pid, record_id $record_id, displays $displays");

DEFINE(PROJECT_PID, $pid);
$user = USERID;

if (empty($pid)) {
    echo "<h6>The displays are associated with a project.  Please enter a project and try again!</h6>";
    return;
}
if (empty($record_id)) {
    echo "<h6>The displays are associated with a record. Please select a record and try again!</h6>";
    return;
}

// Retrieve a list of all setups saved
list($configNames, $config_info) = getConfigs();

// If a display list was no given via GET, display all displays
if (empty($displays)) {
    $displayList = $configNames;
} else {
    $displayList = explode(',', $displays);
}


?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <title>Display table</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/EDT.css"); ?>" />
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>


        <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.flash.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.html5.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.print.min.js"></script>

    </head>
    <body>
        <div class="container">
            <button id="print_button" class="btn btn-primary pull-right hidden-print" onclick="window.print();"><span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print</button>
            <br>
            <h3 style="text-align: center"><?php echo getTitle(); ?></h3>

            <?php echo getAllDisplays(); ?>

        </div>  <!-- END CONTAINER -->
    </body>
</html>

<script>

function toggleButton(buttonName) {
    var display = document.getElementById(buttonName + '_collapse');
    if (display.style.display === 'none') {
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

$(document).ready(function() {

    var tables = document.getElementsByClassName("table");
    for (var ncnt = 0; ncnt < tables.length; ncnt++) {
        var tableElement = $('#' + tables[ncnt].id);

        tableElement.DataTable({
            "lengthMenu": [ [-1, 10, 25, 50], ["All",10, 25, 50] ],
            dom: 'Bftlp',
            buttons: {
                name: 'primary',
                buttons: ['copy', 'excel', 'pdf',
                    {
                        extend: 'print',
                        customize: function (win) {
                            $(win.document.body)
                                .css('font-size', '12pt');
                            $(win.document.body).find('table')
                                .addClass('compact')
                                .css('font-size', 'inherit');
                        }
                    }
                ]
            }
        });

        $(".dt-buttons").css("left", 30);
        $(".dt-buttons").addClass('hidden-print');
        $(".dataTables_filter").addClass('hidden-print');
        $(".dataTables_length").addClass('hidden-print');
    }
});

</script>