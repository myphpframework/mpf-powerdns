<?php

namespace PowerDNS\Exception;

use \MPF\Text;

class InvalidDomain extends \Exception {

    public function __construct($name) {
        parent::__construct(Text::byXml('powerdns')->get('invalidDomain', array('Replace' => array('name' => $name))));
    }

}