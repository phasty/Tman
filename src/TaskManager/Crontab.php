<?php
namespace Tman\TaskManager {
    class Crontab implements IMethod {
        
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
            \Tman\TaskManager::getInstance()->scanDir(function ($className) use (&$list, $cleanOutput) {
                $runTimes = $className::getRunTime();
                if (!$runTimes) {
                    return;
                }
                $className = \Tman\TaskManager::fromClassName(substr($className, strlen(\Tman\TaskManager::getTasksNs())));
                foreach ((array)$runTimes as $args =>  $runTime) {
                    if (substr(trim($runTime), 0, 1) === '#' && $cleanOutput) continue;
                    $list []=  "$runTime tman run " . "$className" . (is_string($args) ? " $args" : "");
                }
            });
            echo implode("\n", $list)."\n";
        }
        
        static protected function usage() {
            echo "usage: [-h|--help] [-c|--clean] tasks\n".
                 "\t-h, --help\tShow this usage message\n".
                 "\t-c, --clean\tDo not show commented out lines\n";
        }
    }
}