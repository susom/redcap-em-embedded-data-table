<?php
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

use \ExternalModules;

class EDT extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
    function redcap_every_page_top($project_id) {
        global $record_id, $event_id;
        //$this->emLog("Project ID: " .$project_id, PAGE,$_GET["event_id"], $_GET["id"], $record_id, $event_id);
    }
    */

    function emLog()
    {

        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }
}

?>
