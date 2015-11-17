<?php
namespace Tman\Task {
    interface ITask {
        public function execute();
        static public function getDescription();
    }
}