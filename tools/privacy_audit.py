#!/usr/bin/env python3
"""
Checks that privacy.php names only fields the code can actually collect, and that
no page claims cross-device account saving now the account system is deleted.
Run this after any schema or copy change. Exit code 1 = a real mismatch found.
"""
import re, glob, sys

FORBIDDEN_PRIVACY_CLAIMS = [
    "customer records", "customer contact details", "business name",
    "quotes, jobs", "invoices, support", "expenses", "enquiries",
    "followups", "recurring", "invoice tracking",
]
FORBIDDEN_SITEWIDE_CLAIMS = [
    "across devices", "keep accounts secure", "your saved details",
]
DELETED_FILES = [
    "dashboard.php","admin.php","admin_export.php","settings.php","account.php",
    "invoices.php","invoice.php","customers.php","customer.php","quotes.php",
    "quote.php","jobs.php","recurring_jobs.php","followups.php","expenses.php",
    "enquiries.php","aged_debt.php","export.php","verify_email_notice.php",
    "my_data.php",
]

problems = []

privacy = open('privacy.php', encoding='utf-8').read() if __import__('os').path.exists('privacy.php') else ''
for phrase in FORBIDDEN_PRIVACY_CLAIMS:
    if phrase.lower() in privacy.lower():
        problems.append(f"privacy.php claims '{phrase}', which no live code path collects")

for f in glob.glob('*.php') + glob.glob('app_private/*.php'):
    s = open(f, encoding='utf-8', errors='ignore').read()
    for phrase in FORBIDDEN_SITEWIDE_CLAIMS:
        if phrase.lower() in s.lower():
            problems.append(f"{f}: contains forbidden phrase '{phrase}'")
    for name in DELETED_FILES:
        # a real link, not this audit list itself
        if re.search(r'href="/' + re.escape(name) + r'"', s) or re.search(r"redirect\('/" + re.escape(name) + r"'\)", s):
            problems.append(f"{f}: links to deleted page {name}")

if problems:
    print(f"{len(problems)} PROBLEM(S) FOUND:")
    for p in problems: print("  -", p)
    sys.exit(1)
print("privacy audit: OK, no mismatch between claims and live code")
