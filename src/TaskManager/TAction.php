<?php
namespace Phasty\Tman\TaskManager {
    abstract class TAction implements IMethod {
        protected $cfg = ["logDir" => "", "tasksNs" => "Tasks\\", "tman" => "./tman"];

        public function __construct($config) {
            $this->cfg = array_replace($this->cfg, $config);
        }
        
    }
}