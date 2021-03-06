<?php

namespace FeideConnect\Authentication;

class UserID {

    public $prefix;
    public $local;

    public function __construct($in = null) {

        if (preg_match('/^(.*):(.*?)$/D', $in, $matches)) {
            $this->prefix = $matches[1];
            $this->local = $matches[2];
        } else {
            throw new \Exception('Invalid format of UserID');
        }

    }

}
