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

    function emLog()
    {
        global $module;
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($module->PREFIX, func_get_args(), "INFO");
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
