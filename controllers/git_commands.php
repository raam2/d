<!DOCTYPE html>
<html>
<head>
    <title>Git Command Reference</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 20px; }
        h2 { color: #0f0; }
        pre { background: #222; padding: 10px; border-left: 4px solid #0f0; }
    </style>
</head>
<body>
    <h2>📦 Git Commands for /var/www/html/bharat_accounting</h2>

    <pre>
# Navigate to your project folder
cd /var/www/html/bharat_accounting

# Initialize Git
git init

# Mark folder as safe (required on BOSS Linux)
git config --global --add safe.directory /var/www/html/bharat_accounting

# Rename default branch to main
git branch -m main

# Add your GitHub repo
git remote add origin https://github.com/raam2/d.git

# Add specific folders
git add views/ actions/

# Commit your changes
git commit -m "Push views and actions folders only"

# Push to GitHub
git push -u origin main
    </pre>

    <h2>🔐 GitHub Token Reminder</h2>
    <pre>
# If prompted for password, use a Personal Access Token
Generate one at: https://github.com/settings/tokens
    </pre>
</body>
</html>

