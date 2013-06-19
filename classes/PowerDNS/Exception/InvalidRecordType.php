<?php

namespace PowerDNS\Exception;

use \MPF\Text;

class InvalidRecordType extends \Exception {

    public function __construct($type) {
        parent::__construct(Text::byXml('powerdns')->get('invalidRecordType', array('Replace' => array('type' => $type))));
    }

}