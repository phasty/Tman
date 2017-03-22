<?php
namespace Phasty\Tman {
    use \Phasty\Log\File as log;
    abstract class Task implements \Phasty\Tman\Task\ITask {
        static public $exceptions = [];
        protected $allowMultipleInstances = false;
        protected $canLog  = null;
        protected $verbose = false;
        protected $cfg = ["logDir" => "", "tasksNs" => "Tasks\\"];
        private   $locks   = [];
        /**
         * Асинхронная обработка сигналов - по тикам или через pcntl_async_signals(true) в PHP 7.1
         *
         * @var bool
         */
        protected $useAsyncSignalHandlers = false;

        public function __construct($config) {
            $this->cfg = array_replace($this->cfg, $config);
            $this->setLogging();
            $this->setHandlers();
        }

        /**
         * Устанавливает период срабатывания зарегистрированных обработчиков тиков
         * А до PHP 7.1 вызывает и обработчки внешних сигнала.
         * С PHP 7.1 для асинхронной обработки внешних сигналов нужно использовать  pcntl_async_signals(true)
         * С PHP 5.3 обработчики сигнала можно вызвать явно через pcntl_signal_dispatch()
         * Т.к. в declare передается константа, и выполняется это в момент прогрузки файла, если не спрятано в eval
         * то проще всего переопределить функцию в дочернем классе, если необходимо
         */
        protected function setTicksGlobal() {
            // На самом деле 1 - это очень часто и больно бьет по производительности
            // надо прятать в eval иначе ticks установится глобально в момент чтения файла
            // по аналогии с const
            eval("declare(ticks = 1);");
        }

        /**
         * Устанавливает обработчики внешних сигналов и принцип их обработки -
         * синхронно по вызову pcntl_signal_dispatch()
         * или аснихронно - по тикам или pcntl_async_signals(true)
         */
        protected function setHandlers() {
            set_error_handler([$this, 'errorHandler']);
            register_shutdown_function([$this, 'shutdownHandler']);
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGTERM, [$this, 'sigHandler']);
                pcntl_signal(SIGINT,  [$this, 'sigHandler']);
                pcntl_signal(SIGHUP,  [$this, 'sigHandler']);
            }


            if ($this->useAsyncSignalHandlers) {
                /* Для асинхронной обработки сигналов */
                if (function_exists('pcntl_async_signals')) {
                    // PHP 7.1 and UP
                    // Для асинхронной обработки сигналов вместо тиков используется:
                    pcntl_async_signals(true);
                } else {
                    // PHP under 7.1
                    // php 7.0 не очень ясно срабатывают хендлеры прерываний по тикам
                    // если нет, то надо вешать register_tick_function() и дергать dispatch
                    $this->setTicksGlobal();
                }
            }
            // Если же задана синхронная обработка сигналов,
            // то нужно делать явный вызов pcntl_signal_dispatch() для обработки внешних сигналов
        }

        /**
         * Устанавливает опции для записи логов
         */
        protected function setLogging() {
            $this->canLog = false;
            $class = substr(preg_replace('#\W+#', '.', get_class($this)), strlen($this->cfg["tasksNs"]));
            log::config([ "path" => $this->cfg["logDir"] . "tasks/%Y/%m/%d/",
                          "name" => "$class" ]);
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
         * @param  string $mode Режим открытия файла
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
         *
         * @throws \Exception
         */
        protected function unlock($file) {
            if (!isset($this->locks[ $file ])) {
                throw new \Exception("Попытка разлочить незалоченный файл");
            }
            fclose($this->locks[ $file ]);
            unset($this->locks[ $file ]);
        }

        /**
         * Ставит блокировку и запускает выполнение таски
         *
         * @throws \Exception
         */
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

        protected function logByType($msg, $type) {
            if (!$this->canLog) {
                echo "$msg\n";
                return;
            }
            if ($this->verbose) {
                echo "$msg\n";
            }
            switch ($type) {
                case 'error' : {
                    log::error($msg);
                    break;
                }
                case 'debug' : {
                    log::debug($msg);
                    break;
                }
                default : {
                    log::info($msg);
                }
            }
        }

        /**
         * Производит запись строки в лог-файл
         *
         * @param string $msg
         */
        protected function log($msg) {
            $type = 'info';
            if ($msg instanceof \Exception) {
                $msg = sprintf(
                    "Exception (%d) %s in %s:%d",
                    $msg->getCode(),
                    $msg->getMessage(),
                    $msg->getFile(),
                    $msg->getLine()
                );
                $type = 'error';
            }
            $this->logByType($msg, $type);
        }

        /**
         * Устанавливает режим вывода лога в консоль
         *
         * @param $value
         */
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
