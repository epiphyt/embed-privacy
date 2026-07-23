<?php

// Minimal WP_Error stand-in so `new \WP_Error()` inside the plugin can be
// instantiated in unit tests without loading WordPress.
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /**
         * @var array
         */
        public $errors = [];

        /**
         * @param mixed $code    Error code
         * @param mixed $message Error message
         * @param mixed $data    Error data
         */
        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
            }
        }
    }
}
