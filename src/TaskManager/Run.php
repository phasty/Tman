<?php
namespace Phasty\Tman\TaskManager {
    class Run implements IMethod {
        public function run($argc, array $argv) {
            if (empty($argc)) {
                self::usage();
                return;
            }
            $taskClassName = \Phasty\Tman\TaskManager::getTasksNs() . array_shift($argv);
            $task = \Phasty\Tman\TaskManager::getClassInstance($taskClassName, [ "Phasty\\Tman\\Task\\ITask" ]);
            if (!$task) {
                self::usage();
                return;
            }
            
            $options = getopt("v::", ["time-limit::", "verbose::"]);
            if (!empty($options["time-limit"])) {
                if (!is_numeric($options["time-limit"])) {
                    self::usage();
                    return;
                }
                set_time_limit($options["time-limit"]);
            }
            
            if (isset($options['v']) || isset($options['verbose'])) {
                $task->setVerbose(1);
            }
            
            call_user_func_array([$task, "run"], $argv);
        }
        
        static protected function usage() {
            echo "usage:\t[--time-limit=Number] [-v|--verbose] \"Task name\"\n".
                 "\trun \"task name\"\t--\tTask class name to run\n";
        }
    }
}