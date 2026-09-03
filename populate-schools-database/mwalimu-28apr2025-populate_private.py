import sqlite3
import csv
import os

db_file = 'mwalimu.db'
csv_file = 'private_school.csv'  # Update if your CSV file name differs

if not os.path.isfile(csv_file):
    raise FileNotFoundError(f"CSV file not found: {csv_file}")

conn = sqlite3.connect(db_file)
cur = conn.cursor()

# Mapping CSV headers to DB columns (fixing typo and renaming level_)
header_mapping = {
    'name': 'name',
    'level': 'level',  # Use 'level' as per your request
    'status': 'status',
    'province': 'province',
    'district': 'district',
    'division': 'division',
    'location': 'location',
    'constituency': 'constituency',  # Fix typo from CSV
    'county': 'county',
    'email': 'email',
    'latitude': 'latitude',
    'longitude': 'longitude'
}

with open(csv_file, newline='', encoding='utf-8') as f:
    reader = csv.reader(f)
    csv_headers = next(reader)
    # Remove BOM if present
    if csv_headers[0].startswith('\ufeff'):
        csv_headers[0] = csv_headers[0][1:]
    csv_headers = [h.strip().lower() for h in csv_headers]

    # Map CSV headers to DB columns, raise error if unexpected header
    try:
        db_columns = [header_mapping[h] for h in csv_headers]
    except KeyError as e:
        raise Exception(f"Unexpected CSV header: {e}")

    placeholders = ','.join(['?'] * len(db_columns))
    columns_sql = ','.join(f'"{col}"' for col in db_columns)

    sql = f'''INSERT INTO private_school ({columns_sql}) VALUES ({placeholders})'''

    row_count = 0
    for row in reader:
        # Convert latitude/longitude to float or None safely
        lat_idx = csv_headers.index('latitude')
        long_idx = csv_headers.index('longitude')

        row = list(row)
        row[lat_idx] = float(row[lat_idx]) if row[lat_idx] else None
        row[long_idx] = float(row[long_idx]) if row[long_idx] else None

        cur.execute(sql, row)
        row_count += 1

conn.commit()
conn.close()
print(f"Imported {row_count} rows into private_school Table.")
