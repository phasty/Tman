<?php
namespace Phasty\Tman {
    use \Phasty\Log\File as log;
    abstract class Task implements \Phasty\Tman\Task\ITask {
        static public $exceptions = [];

        protected $allowMultipleInstances = false;
        protected $canLog  = null;
        protected $verbose = false;
        private   $locks   = [];

        public function __construct() {
            $this->setLogging();
            $this->setHandlers();
        }

        protected function setHandlers() {declare(ticks=1);
            set_error_handler([$this, 'errorHandler']);
            register_shutdown_function([$this, 'shutdownHandler']);
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGTERM, [$this, 'sigHandler']);
                pcntl_signal(SIGINT,  [$this, 'sigHandler']);
                pcntl_signal(SIGHUP,  [$this, 'sigHandler']);
            }
        }

        protected function setLogging() {
            $this->canLog = false;
            $class = substr(preg_replace('#\W+#', '.', get_class($this)), strlen(\Phasty\Tman\TaskManager::getTasksNs()));
            log::config([ "path" => DIR_LOGS . "tasks/%Y/%m/%d/",
                          "name" => "$class.log" ]);
            $this->canLog = true;
        }

        public function errorHandler($errNo, $errStr, $errFile, $errLine, $errContext) {
            $this->log("Error ($errNo) $errStr in $errFile:$errLine");
        }

        public function shutdownHandler() {
            $e = error_get_last();
            if (!$e) {
                return;
            }
            $this->log("Shutdown, last error: {$e['file']}:{$e['line']}:{$e['message']}");
        }
        public function sigHandler($sigNo) {
            $this->log("Caught SIG ($sigNo)");
            exit;
        }

        /**
         * Пытается получить блокировку на файл
         *
         * @param  string $file Файл, блокировку к которому нужно получить
         * @param  string $mod  Режим открытия файла
         *
         * @return bool Получена ли блокировка
         */
        protected function tryLock($file, $mode = "r") {
            $handle = fopen($file, $mode);
            if (!$handle) {
                return false;
            }
            if ($return = flock($handle, LOCK_EX | LOCK_NB)) {
                $this->locks[ $file ] = $handle;
            }
            return $return;
        }

        /**
         * Освободить блокировку файла
         *
         * @param  string $file Файл, блокировку которого нужно освободить
         */
        protected function unlock($file) {
            if (!isset($this->locks[ $file ])) {
                throw new \Exception("Попытка разлочить незалоченный файл");
            }
            fclose($this->locks[ $file ]);
            unset($this->locks[ $file ]);
        }

        public function run() {
            try {
                $this->log("Start task");
                if (empty($this->allowMultipleInstances)) {
                    $fp = fopen("/tmp/lock_".str_replace(['\\', '/'], '_', get_called_class()), 'c');
                    if (!flock($fp, LOCK_EX | LOCK_NB)) {
                        throw new \Exception("Cannot start task because another instance is already running.");
                    }
                }
                call_user_func_array([$this, 'execute'], func_get_args());
            } catch (\Exception $e) {
                $this->log($e);
            }
            $this->log("Stop task");
        }

        protected function log($msg) {
            $error = false;
            if ($msg instanceof \Exception) {
                $msg = sprintf(
                    "Exception (%d) %s in %s:%d",
                    $msg->getCode(),
                    $msg->getMessage(),
                    $msg->getFile(),
                    $msg->getLine()
                );
                $error = true;
            }
            if (!$this->canLog) {
                echo "$msg\n";
                return;
            }
            if ($this->verbose) {
                echo "$msg\n";
            }
            ($error) ? log::error($msg) : log::info($msg);
        }
        public function setVerbose($value) {
            $this->verbose = (int)$value;
        }

        abstract public function execute();

        static public function getDescription() {
            return "";
        }

        static public function getRunTime() {
            return null;
        }
    }
}
