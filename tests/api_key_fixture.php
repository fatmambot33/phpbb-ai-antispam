<?php
namespace phpbb\config {
    /** In-memory stand-in only; integration tests use the real uncached DB service. */
    class db_text {
        public $values = [];
        public $writes = 0;
        public function get($name) { return $this->values[$name] ?? null; }
        public function set($name, $value) { $this->values[$name] = $value; $this->writes++; }
        public function delete($name) { unset($this->values[$name]); }
    }
}
