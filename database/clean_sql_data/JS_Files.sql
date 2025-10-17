/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `JS_Files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `JS_Files` VALUES
(1,'invoice_form','document.addEventListener(\"DOMContentLoaded\", function() {\n  const itemsDiv = document.getElementById(\"items\");\n  let rowIndex = 1;\n\n  document.getElementById(\"addRow\").addEventListener(\"click\", function(e) {\n    e.preventDefault();\n    const row = document.createElement(\"div\");\n    row.innerHTML = `\n      HSN: <input name=\"items[${rowIndex}][hsn]\">\n      Qty: <input name=\"items[${rowIndex}][qty]\" type=\"number\" step=\"1\">\n      Rate: <input name=\"items[${rowIndex}][rate]\" type=\"number\" step=\"0.01\">\n      <button class=\"remove-row\">Remove</button>\n    `;\n    itemsDiv.appendChild(row);\n    rowIndex++;\n  });\n\n  itemsDiv.addEventListener(\"click\", function(e) {\n    if (e.target.classList.contains(\"remove-row\")) {\n      e.preventDefault();\n      e.target.parentElement.remove();\n    }\n  });\n});');
