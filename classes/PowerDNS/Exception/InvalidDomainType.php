<?php

namespace PowerDNS\Exception;

use \MPF\Text;

class InvalidDomainType extends \Exception {

    public function __construct($type) {
        parent::__construct(Text::byXml('powerdns')->get('invalidDomainType', array('Replace' => array('type' => $type))));
    }

}