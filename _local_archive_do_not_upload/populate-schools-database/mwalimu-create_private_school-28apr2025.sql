CREATE TABLE private_school (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    level TEXT NOT NULL,
    status TEXT NOT NULL,
    province TEXT NOT NULL,
    district TEXT NOT NULL,
    division TEXT NOT NULL,
    location TEXT NOT NULL,
    constituency TEXT NOT NULL,
    county TEXT NOT NULL,
    email TEXT,
    latitude REAL,
    longitude REAL,
    created_at DATETIME
);