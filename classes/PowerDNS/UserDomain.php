<?php

namespace PowerDNS;


/**
 * Represent a table structure in the database
 *
 * @object \PowerDNS\UserDomain
 * @table user_domains
 * @database powerdns
 */
class UserDomain extends \MPF\Db\Model {

    /**
     * @type integer unsigned
     */
    protected $userId;

    /**
     * @type varchar 75
     */
    protected $groupId;

    /**
     * @type integer
     */
    protected $domain_id;

    public static function byDomain(\PowerDNS\Domain $domain) {
        $result = self::byField(self::generateField('domain_id', $domain->getId()));

        if ($result->rowsTotal == 0) {
          $result->free();
          return null;
        }

        $userDomain = $result->fetch();
        $result->free();
        return $userDomain;
    }

    /**
     *
     * @param \MPF\User $user
     * @param \PowerDNS\Domain $domain
     * @return \PowerDNS\UserDomain
     */
    public static function create(\MPF\User $user, \PowerDNS\Domain $domain, $groupId=0) {
        $newUserDomain = new \PowerDNS\UserDomain();
        $newUserDomain->userId = $user->getId();
        if ($groupId != 0) {
            $newUserDomain->groupId = $groupId;
        }
        $newUserDomain->domain_id = $domain->getId();

        return $newUserDomain;
    }

    public function setGroupId($groupId) {
        $this->groupId = $groupId;
    }
}