<?php
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

require APP_PATH_DOCROOT . "ControlCenter/header.php";


if (!SUPER_USER) {
    ?>
    <div class="jumbotron text-center">
        <h3><span class="glyphicon glyphicon-exclamation-sign"></span> This utility is available for all projects.</h3>
    </div>
    <?php
    exit();
}

?>

<h3>Embedded Table Instructions</h3>
    <p>
        This External Module is available for each Redcap project.  Users will be required to setup each embedded table
        individually.  Each table is highly customizable allowing users to create tables of repeatable form data, tables
        of data from different events/arms or even bring in data from different projects.
    </p>
    <p>
    </p>
<br>




