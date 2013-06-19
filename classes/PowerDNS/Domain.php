<?php

namespace PowerDNS;

/**
 * Represent a table structure in the database
 *
 * @object \PowerDNS\Domain
 * @table domains
 * @database powerdns
 */
class Domain extends \MPF\Db\Model {
    const TYPE_NATIVE = 'NATIVE';
    const TYPE_MASTER = 'MASTER';
    const TYPE_SLAVE = 'SLAVE';
    const TYPE_SUPERSLAVE = 'SUPERSLAVE';

    /**
     * @primaryKey
     * @readonly
     * @type integer unsigned
     */
    protected $id;

    /**
     * @readonly
     * @type varchar 255
     */
    protected $name;

    /**
     * @type varchar 128
     */
    protected $master = null;

    /**
     * @type integer unsigned
     */
    protected $last_check = null;

    /**
     * @type varchar 6
     */
    protected $type;

    /**
     * @type integer
     */
    protected $notified_serial;

    /**
     * @type varchar 40
     */
    protected $account = null;

    /**
     *
     * @type foreign
     * @table user_domains
     * @linkname domain_id
     * @relation onetoone
     */
    protected $groupId=0;

    /**
     *
     * @type foreign
     * @table user_domains
     * @linkname domain_id
     * @relation onetoone
     */
    protected $userId=0;

    /**
     * Records for the Zone
     *
     * @type foreign
     */
    protected $records;

    /**
     * Creates a new Domain
     *
     * @param string $name
     * @param string $type
     * @return \PowerDNS\Domain
     */
    public static function create($name, $type) {
        $newDomain = new Domain();
        $newDomain->setName($name);
        $newDomain->setType($type);
        return $newDomain;
    }

    /**
     *
     * @param $id
     * @return \PowerDNS\Domain
     */
    public static function byId($id) {
        $result = self::byField(self::generateField('id', $id));

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        $domain = $result->fetch();
        $result->free();
        return $domain;
    }

    /**
     *
     * @param string $name
     * @return \PowerDNS\Domain
     */
    public static function byName($name) {
        $result = self::byField(self::generateField('name', $name));

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        $domain = $result->fetch();
        $result->free();
        return $domain;
    }

    /**
     *
     * @param \MPF\User $user
     * @return \MPF\Db\ModelResult
     */
    public static function byUser(\MPF\User $user, $groupId=0, \MPF\Db\Page $page=null) {
        $userId = $user->getField('id');
        $userId->setLinkFieldName('userId');

        $knownFields = array($userId);
        if ($groupId != 0) {
            $customField = new \MPF\Db\Field(null, 'groupId', $groupId, array());
            $customField->setLinkFieldName('groupId');
            $knownFields[] = $customField;
        }

        $domainId = self::generateField('id');
        $domainId->setLinkFieldName('domain_id');

        $linkTable = new \MPF\Db\ModelLinkTable($knownFields, $domainId, 'powerdns', 'user_domains');
        $result = self::byLinkTable($linkTable, $page);

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        return $result;
    }

    /**
     *
     * @return integer
     */
    public function getOwnerId() {
        return (int)$this->userId;
    }

    /**
     * Returns all the records for the domain
     *
     * @return \PowderDNS\Record
     */
    public function getRecords() {
        $this->loadRecords();

        return $this->records;
    }

    /**
     * Sets the name of the domain
     * @throws \PowerDNS\Exception\InvalidDomain
     * @param string $name
     * @return void
     */
    public function setName($name) {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-\_]{1,62}\.[a-zA-Z0-9]{2,}$/', $name) || strlen($name) > 253) {
            throw new Exception\InvalidDomain($name);
        }

        $this->name = $name;
    }

    /**
     * Sets the name of the domain
     * @throws \PowerDNS\Exception\InvalidDomainType
     * @param string $name
     * @return void
     */
    public function setType($type) {
        if (!in_array($type, array(self::TYPE_NATIVE, self::TYPE_MASTER, self::TYPE_SLAVE, self::TYPE_SUPERSLAVE))) {
            throw new Exception\InvalidDomainType($type);
        }

        $this->type = $type;
    }

    public function getName() {
        return $this->name;
    }

    final public function deleteRecords() {
        $this->loadRecords();
        foreach ($this->records as $record) {
            $record->delete();
        }
        $this->records = null;
    }

    /**
     * Lazy load records
     */
    final private function loadRecords() {
        if ($this->records === null) {
            $this->records = array();
            $result = \PowerDNS\Record::byDomain($this);
            if ($result) {
                while($record = $result->fetch()) {
                    $this->records[] = $record;
                }
                $result->free();
            }
        }
    }

    public function save(\MPF\User $user, $groupId=0) {
        $dbLayer = \MPF\Db::byName($this->getDatabase());
        $dbLayer->transactionStart();

        try {
            $isNew = $this->isNew();
            parent::save();

            if ($isNew) {
                $newUserDomain = \PowerDNS\UserDomain::create($user, $this, $groupId);
                $newUserDomain->save();
            } else {
                $userDomain = \PowerDNS\UserDomain::byDomain($this);
                $userDomain->setGroupId($groupId);
                $userDomain->save();
            }
        } catch (\Exception $e) {
            $dbLayer->transactionRollback();
            throw $e;
        }

        $dbLayer->transactionCommit();
    }

    public function toArray($fetchRecords=true) {
        $domain = parent::toArray();

        if (!$fetchRecords) {
            return $domain;
        }

        $this->loadRecords();
        $records = array();
        foreach ($this->records as $record) {
            $records[] = $record->toArray();
        }
        $domain['records'] = $records;

        return $domain;
    }
}