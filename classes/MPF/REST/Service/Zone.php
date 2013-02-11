<?php

namespace MPF\REST\Service;

class Zone extends \MPF\REST\Service {
    protected function options($id, $action) {
        $this->setResponseCode(self::HTTPCODE_OK);

        //$options = 'GET,POST,PUT,DELETE,OPTIONS,HEAD';
        $response = array('allow' => array('OPTIONS'));
        if ($action) {
            $response['allow'][] = 'GET';
            $response['arguments'] = array(
                'GET' => array()
            );
        } elseif ($id == null) {
            $response['allow'][] = 'GET';
            $response['arguments'] = array(
                'GET' => array(
                    'type' => 'optional',
                    'groupId' => 'optional',
                    'page' => array(
                        'number' => 'optional',
                        'amount' => 'optional',
                    ),
                    'records' => 'option',
                ),
            );
        } else {
            $response['allow'][] = 'POST';
            $response['allow'][] = 'DELETE';
            $response['allow'][] = 'GET';
            $response['allow'][] = 'PUT';
            $response['arguments'] = array(
                'GET' => array('type' => 'optional', 'includeRecords' => 'optional'),
                'PUT' => array('type' => 'optional', 'groupId' => 'optional', 'records' => 'optional'),
                'POST' => array(
                    'type' => 'required',
                    'groupId' => 'optional',
                    'records' => 'optional',
                ),
            );
        }

        header('Allow: '.implode(',', $response['allow']));
        return $response;
    }

    protected function update($id, $data) {
        $this->validate(array('PUT'), array());

        $zone = \PowerDNS\Domain::byName($id);
        if (!$zone) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($zone)) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        try {
            $dbLayer = \MPF\Db::byName($zone->getDatabase());
            $dbLayer->transactionStart();

            $recordUpdate = false;
            foreach ($data as $key => $value) {
                switch ($key) {
                    case 'records':
                        $recordUpdate = true;
                        if (array_key_exists('records', $data)) {
                            $hasRecords = true;

                            $zone->deleteRecords();

                            foreach ($data['records'] as $record) {
                                 $newRecord = \PowerDNS\Record::create($zone, $record['name'], $record['type'], $record['content']);
                                 $newRecord->save();
                            }
                        }
                        break;
                    case 'type':
                        $zone->setType($value);
                        $zone->save($this->user, @$data['groupId']);
                        break;
                    default:
                        continue;
                        break;
                }
            }

            $dbLayer->transactionCommit();
            $this->setResponseCode(self::HTTPCODE_OK);
            return $zone->toArray($recordUpdate);

        } catch (\PowerDNS\Exception\InvalidDomainType $e) {
            $dbLayer->transactionRollback();
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        } catch (\Exception $e) {
            $dbLayer->transactionRollback();
            throw $e;
        }
    }

    protected function create($id, $data) {
        $this->validate(array('POST'), array('type'));
        $name = (!$id ? @$data['name'] : $id);

        try {
            $newZone = \PowerDNS\Domain::create($name, $data['type']);
            $dbLayer = \MPF\Db::byName($newZone->getDatabase());
            $dbLayer->transactionStart();

            $newZone->save($this->user, @$data['groupId']);

            $hasRecords = false;
            if (array_key_exists('records', $data)) {
                $hasRecords = true;
                foreach ($data['records'] as $record) {
                     $newRecord = \PowerDNS\Record::create($newZone, $record['name'], $record['type'], $record['content']);
                     $newRecord->save();
                }
            }

            $dbLayer->transactionCommit();
            $this->setResponseCode(self::HTTPCODE_CREATED);
            return $newZone->toArray($hasRecords);

        } catch (\PowerDNS\Exception\InvalidDomain $e) {
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        } catch (\PowerDNS\Exception\InvalidDomainType $e) {
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        } catch (\PowerDNS\Exception\InvalidRecordType $e) {
            $this->setResponseCode(self::HTTPCODE_BAD_REQUEST);
            return array('errors' => array(
                array('code' => self::HTTPCODE_BAD_REQUEST, 'msg' => $e->getMessage())
            ));
        } catch (\MPF\Db\Exception\DuplicateEntry $e) {
            $this->setResponseCode(self::HTTPCODE_CONFLICT);
            return array('errors' => array(
                array('code' => self::HTTPCODE_CONFLICT, 'msg' => $e->getMessage())
            ));
        }
    }

    protected function records($id, $data) {
        $this->validate(array('GET'), array());

        $zone = \PowerDNS\Domain::byName($id);
        if (!$zone) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($zone)) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        $zoneRecords = $zone->getRecords();
        $records = array();
        foreach ($zoneRecords as $record) {
            $records[] = $record->toArray();
        }

        $this->setResponseCode(self::HTTPCODE_OK);
        return $records;
    }

    protected function delete($id) {
        $this->validate(array('DELETE'), array());

        $zone = \PowerDNS\Domain::byName($id);
        if (!$zone) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($zone)) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        $zone->delete();

        $this->setResponseCode(self::HTTPCODE_NO_CONTENT);
    }

    protected function retrieve($id, $data) {
        $this->validate(array('GET'), array());

        if (!$id) {
            $fetchRecords = array_key_exists('includeRecords', $data);
            $groupId = (array_key_exists('groupId', $data) ? $data['groupId'] : 0);

            $page = null;
            if (array_key_exists('page', $data) ) {
                $pageNumber = (array_key_exists('number', $data['page']) ? (int)$data['page']['number'] : 1);
                $page = (array_key_exists('page', $data) ? new \MPF\Db\Page($pageNumber, $data['page']['amount']) : null);
            }

            $result = \PowerDNS\Domain::byUser($this->user, $groupId, $page);

            $domains = array();
            if ($result) {
                while ($domain = $result->fetch()) {
                    $domains[] = $domain->toArray($fetchRecords);
                }
            }

            // if we request pages we add a special http header for the total records found
            if ($page) {
                header('X-MPF-Total-Entries: '.$page->total);
            }

            $this->setResponseCode(self::HTTPCODE_OK);
            return $domains;
        }

        $zone = \PowerDNS\Domain::byName($id);
        if (!$zone) {
            $this->setResponseCode(self::HTTPCODE_NOT_FOUND);
            return;
        }

        if (!$this->isOwner($zone)) {
            $this->setResponseCode(self::HTTPCODE_UNAUTHORIZED);
            return;
        }

        $this->setResponseCode(self::HTTPCODE_OK);
        return $zone->toArray();
    }

    protected function isOwner(\PowerDNS\Domain $domain) {
        if ($this->user->getId() == $domain->getOwnerId()) {
            return true;
        }
        return false;
    }
}
