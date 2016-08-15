# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 127.0.0.1 (MySQL 5.5.44-0+deb8u1)
# Datenbank: auth
# Erstellungsdauer: 2015-10-10 13:52:01 +0000
# ************************************************************

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;



# Export von Tabelle _auth_roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_role`;

CREATE TABLE `_auth_role` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


# Export von Tabelle _auth_role_children
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_role_child`;

CREATE TABLE `_auth_role_child` (
  `role_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`child_id`),
  KEY `IDX_EA0E6E10D60322AC` (`role_id`),
  KEY `IDX_EA0E6E10DD62C21B` (`child_id`),
  CONSTRAINT `FK_EA0E6E10D60322AC` FOREIGN KEY (`role_id`) REFERENCES `_auth_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_EA0E6E10DD62C21B` FOREIGN KEY (`child_id`) REFERENCES `_auth_role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;




# Export von Tabelle _auth_role_resource
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_role_resource`;

CREATE TABLE `_auth_role_resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned DEFAULT NULL,
  `resource` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `IDX_7671E6A2D60322AC` (`role_id`),
  CONSTRAINT `FK_7671E6A2D60322AC` FOREIGN KEY (`role_id`) REFERENCES `_auth_role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;



# Export von Tabelle _auth_user_roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_user_role`;

CREATE TABLE `_auth_user_role` (
  `authuserentity_id` int(10) unsigned NOT NULL,
  `authrolesentity_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`authuserentity_id`,`authrolesentity_id`),
  KEY `IDX_37223FC7EB2BB790` (`authuserentity_id`),
  KEY `IDX_37223FC72D73D1E1` (`authrolesentity_id`),
  CONSTRAINT `FK_37223FC72D73D1E1` FOREIGN KEY (`authrolesentity_id`) REFERENCES `_auth_role` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_37223FC7EB2BB790` FOREIGN KEY (`authuserentity_id`) REFERENCES `_auth_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;






# Export von Tabelle _auth_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_user`;

CREATE TABLE `_auth_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `salutation` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `surname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci DEFAULT 'pwchange',
  `lastLogin` datetime DEFAULT NULL,
  `failedLogins` int(5) unsigned DEFAULT '0',
  `lastFailedLogin` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;




# Export von Tabelle _auth_user_options
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_user_option`;

CREATE TABLE `_auth_user_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_87601593A76ED395` (`user_id`),
  CONSTRAINT `FK_87601593A76ED395` FOREIGN KEY (`user_id`) REFERENCES `_auth_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


# Export von Tabelle _auth_protocol
# ------------------------------------------------------------

DROP TABLE IF EXISTS `_auth_protocol`;

CREATE TABLE `_auth_protocol` (
  `id`      INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED          DEFAULT NULL,
  `key`     VARCHAR(32)               DEFAULT NULL,
  `value`   VARCHAR(32)               DEFAULT NULL,
  `date`    DATETIME                  DEFAULT NULL,
  `ip`      VARCHAR(32)               DEFAULT NULL,
  `agent`   VARCHAR(255)              DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_87601593A76ED399` (`user_id`),
  CONSTRAINT `FK_87601593A76ED399` FOREIGN KEY (`user_id`) REFERENCES `_auth_user` (`id`)
    ON DELETE CASCADE
)
  ENGINE = InnoDB
  AUTO_INCREMENT = 25
  DEFAULT CHARSET = utf8;


/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
