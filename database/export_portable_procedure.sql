USE gst_accounting;
DROP PROCEDURE IF EXISTS export_portable;

DELIMITER $$

CREATE PROCEDURE export_portable(IN db_name VARCHAR(64))
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE t VARCHAR(64);

  DECLARE cur CURSOR FOR
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = db_name
      AND table_type = 'BASE TABLE';

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO t;
    IF done THEN
      LEAVE read_loop;
    END IF;

    -- Build column identifier list (skip generated columns)
    SET @cols_ident := NULL;
    SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY ordinal_position)
      INTO @cols_ident
    FROM information_schema.columns
    WHERE table_schema = db_name
      AND table_name = t
      AND EXTRA NOT LIKE '%GENERATED%';

    -- Build per-column value expressions, data-type aware
    -- Note: we return SQL expressions (not quoted strings) that will be evaluated per row.
    SET @vals_args := NULL;
    SELECT GROUP_CONCAT(
             CASE
               WHEN data_type IN ('int','bigint','smallint','mediumint','tinyint',
                                  'decimal','numeric','float','double','real')
                 THEN CONCAT('IF(`', column_name, '` IS NULL, ''NULL'', CAST(`', column_name, '` AS CHAR))')
               WHEN data_type IN ('date')
                 THEN CONCAT('IF(`', column_name, '` IS NULL OR `', column_name, '` = ''0000-00-00'', ''NULL'', QUOTE(`', column_name, '`))')
               WHEN data_type IN ('datetime','timestamp','time','year')
                 THEN CONCAT('IF(`', column_name, '` IS NULL, ''NULL'', QUOTE(`', column_name, '`))')
               ELSE
                 -- strings / text / enums / json
                 CONCAT('IF(`', column_name, '` IS NULL, ''NULL'', QUOTE(`', column_name, '`))')
             END
             ORDER BY ordinal_position SEPARATOR ', ')
      INTO @vals_args
    FROM information_schema.columns
    WHERE table_schema = db_name
      AND table_name = t
      AND EXTRA NOT LIKE '%GENERATED%';

    -- Compose dynamic SQL: CONCAT_WS inserts commas between evaluated expressions
    SET @sql = CONCAT(
      'SELECT CONCAT(''INSERT INTO `', t, '` (', @cols_ident, ') VALUES ('', ',
      'CONCAT_WS('', '', ', @vals_args, '), ',
      ''');'') FROM `', t, '`'
    );

    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

  END LOOP;

  CLOSE cur;
END$$

DELIMITER ;
