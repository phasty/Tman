<?php
namespace Phasty\Tman\TaskManager {
    class Crontab extends TAction {
        
        public function run($argc, array $argv) {
            $options = getopt("c::h::", ["clean:", "help::"]);
            if (isset($options[ 'h' ]) || isset($options[ 'help' ])) {
                self::usage();
                return;
            }
            $cleanOutput = false;
            if (isset($options[ 'c' ]) || isset($options[ 'clean' ])) {
                $cleanOutput = true;
            }
            $list = [];
            \Phasty\Tman\TaskManager::getInstance()->scanDir(function ($className) use (&$list, $cleanOutput) {
                $runTimes = $className::getRunTime();
                if (!$runTimes) {
                    return;
                }
                $className = \Phasty\Tman\TaskManager::fromClassName(substr($className, strlen($this->cfg["tasksNs"])));
                foreach ((array)$runTimes as $args =>  $runTime) {
                    if (substr(trim($runTime), 0, 1) === '#' && $cleanOutput) continue;
                    $list []=  "$runTime " . $this->cfg["tman"] . " run " . "$className" . (is_string($args) ? " $args" : "");
                }
            });
            if (!empty($list)) {
                echo implode(" #tman:" . $this->cfg["tman"] . "\n", $list)." #tman:" . $this->cfg["tman"] . "\n";
            }
        }
        
        static protected function usage() {
            echo "usage: [-h|--help] [-c|--clean] tasks\n".
                 "\t-h, --help\tShow this usage message\n".
                 "\t-c, --clean\tDo not show commented out lines\n";
        }
    }
}