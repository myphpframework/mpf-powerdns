<?php

namespace MPF\REST\Service;

class Record extends \MPF\REST\Service {
    protected function options($id, $action) {
        $this->setResponseCode(self::HTTPCODE_OK);

        //$options = 'GET,POST,PUT,DELETE,OPTIONS,HEAD';
        $response = array('allow' => array('OPTIONS'));
        if ($id == null) {
            $response['allow'][] = 'GET';
            $response['arguments'] = array(
                'search' => 'string',
            );
        } else {

        }

        header('Allow: '.implode(',', $response['allow']));
        return $response;
    }

    protected function update($id, $data) {
        $this->validate(array('PUT'), array());

        $record = \PowerDNS\Record::byId((int)$id);
        if (!$record) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($record->getDomain())) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        try {
            if (array_key_exists('name', $data)) {
                $record->setName($data['name']);
            }

            if (array_key_exists('type', $data)) {
                $record->setType($data['type']);
            }

            if (array_key_exists('content', $data)) {
                $record->setContent($data['content']);
            }

            if (array_key_exists('ttl', $data)) {
                $record->setTTL($data['ttl']);
            }

            if (array_key_exists('prio', $data)) {
                $record->setPrio($data['prio']);
            }

            $record->save();
            return $record->toArray();

        } catch (\PowerDNS\Exception\InvalidRecordType $e) {
            $dbLayer->transactionRollback();
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        }
    }

    protected function create($id, $data) {
        $this->validate(array('POST'), array('domain', 'name', 'type', 'content'));

        if (0 !== (int)$data['domain']) {
            $zone = \PowerDNS\Domain::byId($data['domain']);
        } else {
            $zone = \PowerDNS\Domain::byName($data['domain']);
        }

        if (!$zone) {
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => \MPF\Text::byXml('powerdns')->get('invalidZone'))
            ));
            return;
        }

        $record = \PowerDNS\Record::create($domain, $name, $type, $content);
        try {
            if (array_key_exists('ttl', $data)) {
                $record->setTTL($data['ttl']);
            }

            if (array_key_exists('prio', $data)) {
                $record->setPrio($data['prio']);
            }

            $record->save();
            return $record->toArray();

        } catch (\PowerDNS\Exception\InvalidRecordType $e) {
            $dbLayer->transactionRollback();
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        }
    }

    protected function delete($id) {
        $this->validate(array('DELETE'), array());

        $record = \PowerDNS\Record::byId((int)$id);
        if (!$record) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($record->getDomain())) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        $record->delete();

        $this->setResponseCode(self::HTTPCODE_NO_CONTENT);
    }

    protected function retrieve($id, $data) {
        $this->validate(array('GET'), array());

        if (0 === (int)$id) {
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => \MPF\Text::byXml('powerdns')->get('invalidRecordId'))
            ));
        }

        $record = \PowerDNS\Record::byId((int)$id);
        if (!$record) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($record->getDomain())) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        $this->setResponseCode(self::HTTPCODE_OK);
        return $record->toArray();
    }

    protected function isOwner(\PowerDNS\Domain $domain) {
        if ($this->user->getId() == $domain->getOwnerId()) {
            return true;
        }
        return false;
    }
}
