-- Atareao WordPress Database Initialization
-- This file runs automatically when MySQL container starts for the first time

-- Set timezone
SET time_zone = '+01:00';

-- Create additional user if needed
-- CREATE USER 'atareao_readonly'@'%' IDENTIFIED BY 'readonly_password';
-- GRANT SELECT ON atareao_wp.* TO 'atareao_readonly'@'%';

-- Performance optimizations
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
SET GLOBAL innodb_log_file_size = 67108864;     -- 64MB
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL sync_binlog = 0;

-- WordPress optimizations
SET GLOBAL max_allowed_packet = 67108864;        -- 64MB
SET GLOBAL interactive_timeout = 300;
SET GLOBAL wait_timeout = 300;

FLUSH PRIVILEGES;