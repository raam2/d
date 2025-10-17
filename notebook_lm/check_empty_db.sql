SET @db := 'gst_notebook_lm';

SELECT 'TABLE' AS object_type, table_name AS object_name
FROM information_schema.tables
WHERE table_schema=@db
UNION ALL
SELECT 'VIEW', table_name
FROM information_schema.views
WHERE table_schema=@db
UNION ALL
SELECT routine_type, routine_name
FROM information_schema.routines
WHERE routine_schema=@db
UNION ALL
SELECT 'TRIGGER', trigger_name
FROM information_schema.triggers
WHERE trigger_schema=@db
UNION ALL
SELECT 'EVENT', event_name
FROM information_schema.events
WHERE event_schema=@db
UNION ALL
SELECT 'SEQUENCE', table_name
FROM information_schema.tables
WHERE table_schema=@db AND engine='SEQUENCE';
