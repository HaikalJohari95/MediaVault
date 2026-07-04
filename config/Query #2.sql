CREATE TRIGGER after_update_file
AFTER UPDATE ON multimedia_files
FOR EACH ROW
INSERT INTO transaction_audit_log (operation_type, timestamp, user_id, file_id, outcome)
VALUES ('UPDATE', NOW(), NEW.user_id, NEW.file_id, 'SUCCESS');