<?php
namespace Tman\Task {
    class Code extends \Tman\Task {
        public function execute() {
            if (func_num_args() > 1) {
                return;
            }
            $code = func_get_arg(0);
            eval($code);
        }

        static public function usage() {
            echo "tman run Code 'php code'";
        }

        static public function getDescription() {
            return "Выполнить код";
        }
    }
}
