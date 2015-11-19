<?php
namespace Phasty\Tman {
    class TaskManager {
        static protected $shortOptionsDecl = "r:";
        static protected $fullOptionsDecl = array(
            "run:"
        );
        static protected $cfg = [ "logDir"   => getcwd(), 
                                  "tasksDir" => getcwd(), 
                                  "tasksNs"  => "Tasks\\" ];
        
        static protected $instance = null;
        protected $options = [];
        protected $argc = null;
        protected $argv = null;
        
        public function __construct($argc, $argv, $config=[]) {
            $this->options = getopt(self::$shortOptionsDecl, self::$fullOptionsDecl);
            $count = count($argv);
            for ($i = 0; $i < $count; $i++) {
                if (substr($argv[ $i ], 0, 1) == '-') {
                    unset($argv[ $i ]);
                }
            }
            $this->argv = array_values($argv);
            $this->argc = count($this->argv);
            array_replace(self::$cfg, $config);
            if (!self::$instance instanceof static) {
                self::$instance = $this;
            }
        }

        public function run() {
            if ($this->argc == 1) {
                self::usage();
                return;
            }
            
            $className = get_class($this) . "\\" . ucfirst($this->argv[1]);
            
            if (!class_exists($className)) {
                self::usage();
                return;
            }
            
            $class = self::getClassInstance($className, [ "Phasty\\Tman\\TaskManager\\IMethod" ]);
            
            if (!$class) {
                self::usage();
                return;
            }
            
            $argv = $this->argv;
            array_shift($argv);
            array_shift($argv);
            $class->setConfig(self::$cfg);
            $class->run($this->argc - 2, $argv);
        }
        
        public function scanDir(callable $callback, $tasksDir = null, $getPretendings = false) {
            if (empty($tasksDir)) {
                $tasksDir = self::$cfg["tasksDir"];
            }
            foreach (glob("$tasksDir/*") as $taskFile) {
                if (is_dir($taskFile)) {
                    self::scanDir($callback, $taskFile);
                    continue;
                }
                if (strtolower(pathinfo($taskFile, PATHINFO_EXTENSION)) !== 'php') {
                    continue;
                }
                $className = self::$cfg['tasksNs'] . str_replace(DS, "\\", substr($taskFile, strlen(self::$cfg["tasksDir"]) + 1, -4));
                if (!class_exists($className)) {
                    continue;
                }
                $implements = class_implements($className);
                if (!isset($implements[ "Phasty\\Tman\\Task\\ITask" ]) & !$getPretendings) {
                    continue;
                }
                $callback($className);
            }
        }

        static public function getInstance() {
            return self::$instance;
        }
        
        static public function toClassName($entity) {
            return preg_replace("#\W+#", "\\", $entity);
        }
        
        static public function fromClassName($className) {
            return str_replace("\\", "/", $className);
        }
        
        static public function getClassInstance($className, array $interfaces = []) {
            $className = self::toClassName($className);
            if (!class_exists($className)) {
                echo "Class '$className' is not defined\n";
                return;
            }
            $implements = class_implements($className);
            
            foreach ($interfaces as $interface) {
                if (!isset($implements[ $interface ])) {
                    echo "Class '$className' is incomplete\n";
                    return null;
                }
            }
            return new $className;
        }
        
        static public function usage() {
            echo "usage:\n".
                 "\trun\t\tExecute task with or without parameters\n".
                 "\ttasks\t\tList all available tasks\n".
                 "\tcrontab\t\tShow crontab file content for all tasks\n";
        }
    }
}