<?php
namespace Stanford\EDT;

require_once ("emLoggerTrait.php");

class EDT extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public function __construct()
    {
        parent::__construct();
        $this->emDebug("In constructor");
    }

}

?>
