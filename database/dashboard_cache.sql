-- Dashboard Cache Table (TODO.md P2.3)
-- mysql -u root -p mealing_online < this_file.sql

CREATE TABLE IF NOT EXISTS `dashboard_cache` (
  `cache_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `cache_data` longtext NOT NULL COMMENT 'JSON encoded stats',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`cache_id`),
  UNIQUE KEY `unique_house_month_year` (`house_id`,`month`,`year`),
  KEY `idx_house_expires` (`house_id`,`expires_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean expired cache
DELETE FROM dashboard_cache WHERE expires_at < NOW();

-- Test insert (remove after)
-- INSERT INTO dashboard_cache (house_id, month, year, cache_data, expires_at) 
-- VALUES (1, 10, 2024, '{}', DATE_ADD(NOW(), INTERVAL 1 HOUR));

SELECT 'Dashboard cache table created. Ready for functions.' as status;

