Mail-Queue
==========

This is a natvie mail queue class. You can create mail queue and execute with cron job on background.

SQL
===

CREATE TABLE `log_smtp` (
  `log_smtp_id` int(11) NOT NULL AUTO_INCREMENT,
  `insert_date` datetime NOT NULL,
  `insert_user` int(11) NOT NULL,
  `status` tinyint(3) NOT NULL DEFAULT '1',
  `priority` tinyint(3) NOT NULL DEFAULT '1',
  `send_date` datetime DEFAULT NULL,
  `email_data` text COLLATE utf8_turkish_ci NOT NULL,
  PRIMARY KEY (`log_smtp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2357 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
