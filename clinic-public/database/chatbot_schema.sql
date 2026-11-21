-- Chatbot tables for the CarePoint AI assistant.
-- Run once against the shared clinic_scheduler database:
--   mysql -u root clinic_scheduler < database/chatbot_schema.sql
USE clinic_scheduler;

CREATE TABLE IF NOT EXISTS chat_conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_key VARCHAR(64) NOT NULL UNIQUE,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_chat_last_message (last_message_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender ENUM('user','bot') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_message_conversation
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
  INDEX idx_chat_conversation (conversation_id, id)
) ENGINE=InnoDB;
