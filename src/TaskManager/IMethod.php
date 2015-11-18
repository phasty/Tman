<?php
namespace Phasty\Tman\TaskManager {
    interface IMethod {
        public function run($argc, array $argv);
    }
}