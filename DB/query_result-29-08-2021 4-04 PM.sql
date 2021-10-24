# ************************************************************
# Sequel Pro SQL dump
# Version 4541
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: arogga.xxxxxx.ap-southeast-1.rds.amazonaws.com (MySQL 5.5.5-10.5.12-MariaDB-log)
# Database: arogga_test1
# Generation Time: 2021-08-29 10:04:39 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table t_bags
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_bags`;

CREATE TABLE `t_bags` (
  `b_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `b_ph_id` int(10) unsigned NOT NULL DEFAULT 0,
  `b_zone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `b_no` smallint(5) unsigned NOT NULL DEFAULT 0,
  `b_de_id` int(10) unsigned NOT NULL DEFAULT 0,
  `o_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `o_ids` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`b_id`),
  KEY `ph_id_zone` (`b_ph_id`,`b_zone`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_categories
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_categories`;

CREATE TABLE `t_categories` (
  `c_id` tinyint(3) NOT NULL AUTO_INCREMENT,
  `c_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `c_order` tinyint(3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`c_id`),
  KEY `c_order` (`c_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_collections
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_collections`;

CREATE TABLE `t_collections` (
  `co_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `co_fid` int(10) unsigned NOT NULL,
  `co_tid` int(10) unsigned NOT NULL,
  `o_ids` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `co_amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `co_s_amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `co_status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `co_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `co_bag` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`co_id`),
  KEY `co_fid_co_tid` (`co_fid`,`co_tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_companies
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_companies`;

CREATE TABLE `t_companies` (
  `c_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `c_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_discounts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_discounts`;

CREATE TABLE `t_discounts` (
  `d_id` int(10) NOT NULL AUTO_INCREMENT,
  `d_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `d_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `d_amount` smallint(6) unsigned NOT NULL,
  `d_max` smallint(6) unsigned NOT NULL,
  `d_max_use` mediumint(9) unsigned NOT NULL,
  `d_status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `d_expiry` datetime NOT NULL,
  `u_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`d_id`),
  UNIQUE KEY `d_code` (`d_code`),
  KEY `u_id` (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_generics
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_generics`;

CREATE TABLE `t_generics` (
  `g_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `g_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `precaution` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `indication` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `contra_indication` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `side_effect` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode_of_action` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `interaction` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `pregnancy_category_note` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `adult_dose` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_dose` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `renal_dose` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `administration` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`g_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_generics_v2
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_generics_v2`;

CREATE TABLE `t_generics_v2` (
  `g_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `g_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `g_overview` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `g_quick_tips` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `g_safety_advices` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `g_question_answer` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`g_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_histories
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_histories`;

CREATE TABLE `t_histories` (
  `h_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `h_obj` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `obj_id` int(10) unsigned NOT NULL DEFAULT 0,
  `u_id` int(10) unsigned NOT NULL DEFAULT 0,
  `h_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `h_action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `h_from` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `h_to` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`h_id`),
  KEY `h_obj_obj_id` (`h_obj`,`obj_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_inventory
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_inventory`;

CREATE TABLE `t_inventory` (
  `i_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `i_ph_id` int(10) unsigned NOT NULL,
  `i_m_id` int(10) unsigned NOT NULL,
  `i_price` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `i_qty` int(10) NOT NULL DEFAULT 0,
  `wkly_req` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`i_id`),
  UNIQUE KEY `i_ph_id_i_m_id` (`i_ph_id`,`i_m_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_inventory_meta
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_inventory_meta`;

CREATE TABLE `t_inventory_meta` (
  `meta_id` int(10) NOT NULL AUTO_INCREMENT,
  `i_id` int(10) NOT NULL,
  `meta_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `i_id_meta_key` (`i_id`,`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_later_medicines
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_later_medicines`;

CREATE TABLE `t_later_medicines` (
  `lm_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `o_ph_id` int(10) unsigned NOT NULL,
  `m_id` int(10) unsigned NOT NULL,
  `o_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `total_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `u_id` int(10) unsigned NOT NULL DEFAULT 0,
  `m_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`lm_id`),
  UNIQUE KEY `o_ph_m_id` (`o_ph_id`,`m_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_ledger
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_ledger`;

CREATE TABLE `t_ledger` (
  `l_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `l_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `l_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `l_reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `l_files` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`l_id`),
  KEY `l_type` (`l_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_locations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_locations`;

CREATE TABLE `t_locations` (
  `l_id` int(10) NOT NULL AUTO_INCREMENT,
  `l_division` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_district` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_area` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_postcode` smallint(6) NOT NULL DEFAULT 0,
  `l_de_id` int(10) NOT NULL DEFAULT 0,
  `l_ph_id` int(10) NOT NULL DEFAULT 0,
  `l_zone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `l_lat` decimal(8,6) NOT NULL DEFAULT 0.000000,
  `l_long` decimal(9,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`l_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_logs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_logs`;

CREATE TABLE `t_logs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `u_id` int(10) unsigned NOT NULL DEFAULT 0,
  `log_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `log_ua` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `log_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `log_http_method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `log_uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `log_get` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_post` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_response_code` smallint(3) unsigned NOT NULL DEFAULT 0,
  `log_response` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `log_uri` (`log_uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_logs_backup
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_logs_backup`;

CREATE TABLE `t_logs_backup` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `u_id` int(10) unsigned NOT NULL DEFAULT 0,
  `log_ip` varchar(45) NOT NULL DEFAULT '',
  `log_ua` varchar(255) NOT NULL DEFAULT '',
  `log_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `log_http_method` varchar(10) NOT NULL DEFAULT '',
  `log_uri` varchar(255) NOT NULL DEFAULT '',
  `log_get` mediumtext NOT NULL,
  `log_post` mediumtext NOT NULL,
  `log_response_code` smallint(3) unsigned NOT NULL DEFAULT 0,
  `log_response` mediumtext NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `log_uri` (`log_uri`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table t_medicine_meta
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_medicine_meta`;

CREATE TABLE `t_medicine_meta` (
  `meta_id` int(10) NOT NULL AUTO_INCREMENT,
  `m_id` int(10) unsigned NOT NULL,
  `meta_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `m_id_meta_key` (`m_id`,`meta_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_medicines
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_medicines`;

CREATE TABLE `t_medicines` (
  `m_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `m_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_g_id` int(10) unsigned NOT NULL DEFAULT 0,
  `m_c_id` int(10) unsigned NOT NULL DEFAULT 0,
  `m_form` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_strength` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_price` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `m_d_price` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `m_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'allopathic',
  `m_status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `m_rob` tinyint(1) NOT NULL DEFAULT 1,
  `m_comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_i_comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_u_id` int(11) unsigned NOT NULL DEFAULT 0,
  `m_cat_id` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `m_min` smallint(5) unsigned NOT NULL DEFAULT 1,
  `m_max` smallint(5) unsigned NOT NULL DEFAULT 200,
  PRIMARY KEY (`m_id`),
  KEY `m_name` (`m_name`),
  KEY `m_status` (`m_status`) USING BTREE,
  KEY `m_g_id_m_c_id` (`m_g_id`,`m_c_id`),
  FULLTEXT KEY `ft_m_name` (`m_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_o_medicines
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_o_medicines`;

CREATE TABLE `t_o_medicines` (
  `om_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `o_id` int(10) unsigned NOT NULL,
  `m_id` int(10) unsigned NOT NULL,
  `m_qty` int(10) unsigned NOT NULL,
  `refund_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `damage_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `m_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `m_price` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `m_d_price` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `s_price` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `om_status` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`om_id`),
  UNIQUE KEY `oid_mid` (`o_id`,`m_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_options
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_options`;

CREATE TABLE `t_options` (
  `option_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_order_meta
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_order_meta`;

CREATE TABLE `t_order_meta` (
  `meta_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `o_id` int(10) unsigned NOT NULL,
  `meta_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `oid_metakey` (`o_id`,`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_orders
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_orders`;

CREATE TABLE `t_orders` (
  `o_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `u_id` int(10) unsigned NOT NULL,
  `u_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `u_mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `o_subtotal` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `o_addition` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `o_deduction` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `o_total` decimal(10,2) unsigned NOT NULL DEFAULT 0.00,
  `o_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `o_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `o_delivered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `o_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `o_i_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `o_is_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `o_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `o_lat` decimal(8,6) NOT NULL DEFAULT 0.000000,
  `o_long` decimal(9,6) NOT NULL DEFAULT 0.000000,
  `o_gps_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `o_payment_method` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cod',
  `o_de_id` int(10) unsigned NOT NULL DEFAULT 0,
  `o_ph_id` int(10) unsigned NOT NULL DEFAULT 0,
  `o_priority` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `o_l_id` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`o_id`),
  KEY `u_id` (`u_id`),
  KEY `o_de_id` (`o_de_id`) USING BTREE,
  KEY `o_ph_id` (`o_ph_id`) USING BTREE,
  KEY `o_created` (`o_created`),
  KEY `o_delivered` (`o_delivered`),
  KEY `o_status_o_i_status` (`o_status`,`o_i_status`),
  KEY `o_is_status` (`o_is_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_purchase_request
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_purchase_request`;

CREATE TABLE `t_purchase_request` (
  `tp_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ph_id` int(10) unsigned NOT NULL DEFAULT 0,
  `m_id` int(10) unsigned NOT NULL DEFAULT 0,
  `u_id` int(10) unsigned NOT NULL,
  `qty_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`tp_id`),
  UNIQUE KEY `ph_m_id` (`ph_id`,`m_id`),
  KEY `u_id` (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_purchases
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_purchases`;

CREATE TABLE `t_purchases` (
  `pu_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pu_inv_id` int(10) unsigned NOT NULL DEFAULT 0,
  `pu_ph_id` int(10) unsigned NOT NULL DEFAULT 0,
  `pu_m_id` int(10) unsigned NOT NULL DEFAULT 0,
  `pu_price` decimal(13,4) NOT NULL DEFAULT 0.0000,
  `pu_qty` int(10) NOT NULL DEFAULT 0,
  `m_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pu_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pu_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `m_expiry` date NOT NULL DEFAULT '0000-00-00',
  `m_batch` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`pu_id`),
  KEY `pu_inv_id` (`pu_inv_id`),
  KEY `pu_status` (`pu_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_tokens
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_tokens`;

CREATE TABLE `t_tokens` (
  `t_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `t_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `t_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `t_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `t_ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `t_token` (`t_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_user_meta
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_user_meta`;

CREATE TABLE `t_user_meta` (
  `meta_id` int(10) NOT NULL AUTO_INCREMENT,
  `u_id` int(10) NOT NULL,
  `meta_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `u_id_meta_key` (`u_id`,`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table t_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `t_users`;

CREATE TABLE `t_users` (
  `u_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `u_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `u_mobile` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `u_token` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `u_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `u_lat` decimal(8,6) NOT NULL DEFAULT 0.000000,
  `u_long` decimal(9,6) NOT NULL DEFAULT 0.000000,
  `u_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `u_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `u_role` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `u_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `u_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `u_p_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `u_otp` int(10) unsigned NOT NULL DEFAULT 0,
  `u_otp_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `u_referrer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `u_r_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `u_o_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`u_id`),
  UNIQUE KEY `u_mobile` (`u_mobile`) USING BTREE,
  UNIQUE KEY `u_email` (`u_email`),
  UNIQUE KEY `u_referrer` (`u_referrer`),
  KEY `u_role` (`u_role`) USING BTREE,
  KEY `u_created` (`u_created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
