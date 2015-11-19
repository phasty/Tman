<?php
namespace Phasty\Tman\TaskManager {
    class TAction implements IMethod {
        protected $cfg = ["logDir" => "", "tasksNs" => "Tasks\\"];

        public function setConfig($config) {
            array_replace($this->cfg, $config);
        }
        
    }
}