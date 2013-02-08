
CREATE TABLE IF NOT EXISTS `user_domains` (
  `userId` mediumint(8) unsigned NOT NULL,
  `groupId` varchar(75)  NULL,
  `domain_id` int(11) NOT NULL,.
  UNIQUE KEY `userId_domainId` (`userId`,`domain_id`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
