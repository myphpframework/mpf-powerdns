<?php

namespace PowerDNS;


/**
 * Represent a table structure in the database
 *
 * @object \PowerDNS\Record
 * @table records
 * @database powerdns
 */
class Record extends \MPF\Db\Model {
    const TYPE_SOA = 'SOA';
    const TYPE_NS = 'NS';
    const TYPE_MX = 'MX';
    const TYPE_A = 'A';
    const TYPE_AAAA = 'AAAA';
    const TYPE_CNAME = 'CNAME';
    const TYPE_TXT = 'TXT';
    const TYPE_PTR = 'PTR';
    const TYPE_HWINFO = 'HWINFO';
    const TYPE_SRV = 'SRV';
    const TYPE_NAPTR = 'NAPTR';

    /**
     * @primaryKey
     * @readonly
     * @type integer unsigned
     */
    protected $id;

    /**
     * @type integer unsigned
     * @foreignTable domains
     */
    protected $domain_id;

    /**
     * @type varchar 255
     */
    protected $name;

    /**
     * @type varchar 6
     */
    protected $type;

    /**
     * @type varchar 255
     */
    protected $content;

    /**
     * @type integer unsigned
     */
    protected $ttl = 86400;

    /**
     * @type integer unsigned
     */
    protected $prio = null;

    /**
     * @type integer unsigned
     */
    protected $change_date;

    /**
     *
     * @param \PowerDNS\Domain $domain
     * @param string $name
     * @param string $type
     * @param string $content
     * @return \PowerDNS\Record
     */
    public static function create(\PowerDNS\Domain $domain, $name, $type, $content) {
        $newRecord = new Record();
        $newRecord->setDomain($domain);
        $newRecord->setName($name);
        $newRecord->setType($type);
        $newRecord->setContent($content);
        return $newRecord;
    }

    /**
     *
     * @param $id
     * @return \PowerDNS\Record
     */
    public static function byId($id) {
        $result = self::byField(self::generateField('id', $id));

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        $record = $result->fetch();
        $result->free();
        return $record;
    }

    /**
     *
     * @param $id
     * @return \MPF\Db\ModelResult
     */
    public static function byDomain(\PowerDNS\Domain $domain) {
        $result = self::byField(self::generateField('domain_id', $domain->getId()));

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        return $result;
    }

    public function getDomain() {
        return \PowerDNS\Domain::byId($this->domain_id);
    }

    public function setTTL($ttl=86400) {
        $this->ttl = $ttl;
    }

    public function setDomain(\PowerDNS\Domain $domain) {
        $this->domain_id = $domain->getId();
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setType($type) {
        $type = strtoupper($type);
        if (!in_array($type, array(self::TYPE_SOA,self::TYPE_NS,self::TYPE_MX,self::TYPE_A,self::TYPE_AAAA,self::TYPE_CNAME,self::TYPE_TXT,self::TYPE_PTR,self::TYPE_HWINFO,self::TYPE_SRV,self::TYPE_NAPTR))) {
            throw new Exception\InvalidRecordType($type);
        }
        $this->type = $type;
    }

    public function setContent($content) {
        $this->content = $content;
    }

    public function setPrio($prio) {
        $this->prio = $prio;
    }
}