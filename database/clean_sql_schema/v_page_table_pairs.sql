/*M!999999\- enable the sandbox mode */ 
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_page_table_pairs` AS SELECT
 1 AS `page_id`,
  1 AS `page_name`,
  1 AS `table_name` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_page_table_pairs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_page_table_pairs` AS select `p`.`id` AS `page_id`,`p`.`name` AS `page_name`,`t`.`table_name` AS `table_name` from ((select `information_schema`.`tables`.`TABLE_NAME` AS `table_name` from `information_schema`.`tables` where `information_schema`.`tables`.`TABLE_SCHEMA` = 'gst_accounting' and `information_schema`.`tables`.`TABLE_TYPE` = 'BASE TABLE' and `information_schema`.`tables`.`TABLE_NAME` not in ('Pages','CSS_Files','JS_Files','Users')) `t` join `gst_accounting`.`Pages` `p` on(find_in_set(`t`.`table_name`,`p`.`tables_used`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
