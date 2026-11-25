-- Migration 006: arrotonda gli orari di voto a blocchi di 15 minuti + app_settings
-- Prima: voter_lists.token_used_at e votes.voted_at erano identici al secondo, quindi
-- un elettore poteva essere collegato al proprio voto. Dopo: entrambi arrotondati per
-- difetto allo stesso blocco di 15 minuti (come fa Vote::cast da v0.6.1).
-- Le statistiche orarie e giornaliere NON cambiano. Le registrazioni (voter_lists.created_at)
-- restano esatte.
-- Operazione NON reversibile: fare prima un dump di voter_lists e votes.
-- E' rieseguibile senza effetti collaterali (idempotente).

UPDATE `voter_lists`
   SET `token_used_at` = FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(`token_used_at`) / 900) * 900)
 WHERE `token_used_at` IS NOT NULL;

UPDATE `votes`
   SET `voted_at` = FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(`voted_at`) / 900) * 900);

-- Installazioni create con il vecchio schema.sql non hanno app_settings
CREATE TABLE IF NOT EXISTS `app_settings` (
  `key`        VARCHAR(100) PRIMARY KEY,
  `value`      TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `app_settings` (`key`, `value`) VALUES ('admin_theme', 'electus');
