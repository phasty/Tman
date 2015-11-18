<?php
namespace Phasty\Tman\TaskManager {
    class Tasks implements IMethod {
        protected $showPretends = false;
        protected $rootDirLen = 0;
        
        public function run($argc, array $argv) {
            $options = getopt("p::h::", ["pretend:", "help::"]);
            if (isset($options[ 'h' ]) || isset($options[ 'help' ])) {
                self::usage();
                return;
            }
            $this->showPretends = isset($options[ 'p' ]) || isset($options[ 'pretend' ]);
            $pretend = [];
            $list = [];
            \Phasty\Tman\TaskManager::getInstance()->scanDir(function ($className) use (&$pretend, &$list) {
                $implements = class_implements($className);
                if (!isset($implements[ "Phasty\\Tman\\Task\\ITask" ])) {
                    if ($this->showPretends) {
                        $pretend []= \Tman\TaskManager::fromClassName($className);
                    }
                    return;
                }
                $runTimes = $className::getRunTime();
                $isInCron = 0;
                foreach ((array)$runTimes as $runTime) {
                    if (substr(trim($runTime), 0, 1) === '#') continue;
                    $isInCron++;
                }
                $isInCron = $isInCron ? $isInCron == 1 ? "+\t" : "$isInCron\t" : " \t";
                $list []= $isInCron . self::padString(substr(\Phasty\Tman\TaskManager::fromClassName($className), strlen(\Phasty\Tman\TaskManager::getTasksNs()))) . $className::getDescription();
            });
            if (!empty($list)) {
                $c = count($list);
                echo "$c task" . ($c > 1 ? "s are" : " is") . " accessable:\n";
                echo "\tCron\t" . self::padString("Task") . "Description\n\t";
                echo implode("\n\t", $list), "\n";
            }
            if (!empty($pretend) && $this->showPretends) {
                $c = count($pretend);
                echo "$c class" . ($c > 1 ? "s" : "") . " pretends to be task".($c == 1 ? "" : "s").":\n\t",
                     implode("\n\t", $pretend), "\n";
            }
        }
        static protected function padString($str, $columns = 40) {
            return str_pad($str, $columns, " ", STR_PAD_RIGHT);
        }
        static protected function usage() {
            echo "usage: [-p|--pretend] tasks\n";
        }
    }
}