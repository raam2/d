CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) DEFAULT 1
);

-- Example (replace PASSWORD_HASH):
INSERT INTO users(username,password_hash) VALUES(
  'gstwork',
  '$2y$10$bHpwtMFTzJvGmGPUuecOM.dAPxZMsshofhr4oLETD5Z3GYzqUpRxW'
);
