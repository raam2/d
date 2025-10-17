/*M!999999\- enable the sandbox mode */ 
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_pages_to_tables` AS SELECT
 1 AS `page_name`,
  1 AS `dependent_tables`,
  1 AS `tables_count` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_pages_to_tables`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_pages_to_tables` AS select `p`.`name` AS `page_name`,`p`.`tables_used` AS `dependent_tables`,case when `p`.`tables_used` is null or `p`.`tables_used` = '' then 0 else 1 + octet_length(`p`.`tables_used`) - octet_length(replace(`p`.`tables_used`,',','')) end AS `tables_count` from `Pages` `p` order by case when `p`.`tables_used` is null or `p`.`tables_used` = '' then 0 else 1 + octet_length(`p`.`tables_used`) - octet_length(replace(`p`.`tables_used`,',','')) end desc,`p`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
