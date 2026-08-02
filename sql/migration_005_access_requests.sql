-- Migration 005: richieste di accesso pubbliche
-- Aggiunge una tabella per raccogliere le richieste di account inviate
-- dalla landing page pubblica (form "Richiedi accesso"). Il superadmin
-- le rivede in admin/users.php e crea l'account o le ignora.

CREATE TABLE IF NOT EXISTS `access_requests` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(120)  NOT NULL,
  `email`        VARCHAR(254)  NOT NULL,
  `organization` VARCHAR(190)  NULL,
  `message`      TEXT          NULL,
  `status`       ENUM('pending','approved','dismissed') NOT NULL DEFAULT 'pending',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
