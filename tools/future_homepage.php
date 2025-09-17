<?php
echo "<h1>GST अकाउंटिंग सॉफ्टवेयर – होम</h1>";
?>
<ul style='font-size:1.2em;line-height:1.8'>
    <li><a href='company.php'>कंपनी जोड़ें/सूची</a></li>
    <li><a href='party.php'>ग्राहक/सप्लायर जोड़ें/सूची</a></li>
    <li><a href='items.php'>प्रोडक्ट/सर्विस जोड़ें/सूची</a></li>
    <li><a href='invoices.php'>इनवॉइस बनाएं/देखें</a></li>
    <li><a href='payments.php'>पेमेंट व रसीद</a></li>
    <li><a href='outstanding_report.php'>आउटस्टैंडिंग रिपोर्ट</a></li>
    <li><a href='search_suggestions.php?type=product'>Product Suggestions (JSON)</a></li>
    <li><a href='search_suggestions.php?type=customer'>Customer Suggestions (JSON)</a></li>
    <li><a href='gstr1_export.php'>GSTR-1 Export (CSV)</a></li>
    <li><a href='db_check.php'>DB Check</a></li>
</ul>
<hr>
<p style='font-size:0.96em;color:#888'>© <?= date('Y') ?> GST Accounting – Powered by PHP, MySQL, Linux</p>

