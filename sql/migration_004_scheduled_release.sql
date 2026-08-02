-- Migration 004: pubblicazione automatica schedulata dei risultati
-- Aggiunge la possibilità di impostare una data/ora futura alla quale
-- i risultati di un round vengono pubblicati automaticamente, senza
-- bisogno di cron o worker: il trigger avviene alla prima richiesta HTTP
-- su vote/results.php dopo la data/ora impostata.

ALTER TABLE `event_rounds`
  ADD COLUMN `scheduled_release_at` DATETIME NULL
  COMMENT 'Se impostato e votes_validated=1, pubblica automaticamente i risultati a questa data/ora'
  AFTER `results_released`;
