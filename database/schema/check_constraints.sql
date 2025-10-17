chk_gstin_format	parties	`gstin` regexp '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$'
chk_journal_status	journal_entries	`status` in ('draft','posted','cancelled')
