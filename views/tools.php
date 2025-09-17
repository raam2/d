<h2>🛠️ Workflow Tools</h2>

<!-- Hindi Translation Validator -->
<form method="post" action="actions/run_hindi_validator.php">
    <button type="submit">Validate Hindi_Name</button>
</form>
<form method="post" action="actions/export_missing_hindi.php">
    <button type="submit">Export Missing Hindi_Name</button>
</form>
<form method="post" action="actions/apply_hindi_corrections.php" enctype="multipart/form-data">
    <input type="file" name="corrected_csv" accept=".csv" required>
    <select name="apply">
        <option value="preview">Preview Only</option>
        <option value="update">Apply to DB</option>
    </select>
    <input type="checkbox" name="confirm" required> I confirm updates are safe
    <button type="submit">Run</button>
</form>

<hr>

<!-- Referential Integrity Checker -->
<form method="post" action="actions/run_integrity_check.php">
    <button type="submit">Check Referential Integrity</button>
</form>
<form method="post" action="actions/export_broken_refs.php">
    <button type="submit">Export Broken References</button>
</form>

<hr>

<!-- Normalization Assistant -->
<form method="post" action="actions/run_normalization_check.php">
    <button type="submit">Check Normalization</button>
</form>

<hr>

<!-- Master List Merger -->
<form method="post" action="actions/run_master_merge.php">
    <button type="submit">Merge Corrected Batches</button>
</form>
<form method="post" action="actions/export_merged_master.php">
    <button type="submit">Export Merged Master List</button>
</form>
<form method="post" action="actions/apply_master_corrections.php" enctype="multipart/form-data">
    <input type="file" name="corrected_csv" accept=".csv" required>
    <select name="apply">
        <option value="preview">Preview Only</option>
        <option value="update">Apply to DB</option>
    </select>
    <input type="checkbox" name="confirm" required> I confirm updates are safe
    <button type="submit">Run</button>
</form>

