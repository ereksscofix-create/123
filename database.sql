CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  login TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  name TEXT,
  language TEXT DEFAULT 'ru'
);

CREATE TABLE IF NOT EXISTS countries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name_ru TEXT NOT NULL,
  name_kk TEXT NOT NULL,
  iso_code TEXT
);

CREATE TABLE IF NOT EXISTS trips (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT,
  start_date DATE,
  end_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS trip_countries (
  trip_id INTEGER NOT NULL,
  country_id INTEGER NOT NULL,
  PRIMARY KEY (trip_id, country_id),
  FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE CASCADE,
  FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trip_days (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id INTEGER NOT NULL,
  day_date DATE NOT NULL,
  note TEXT,
  FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trip_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  day_id INTEGER NOT NULL,
  category TEXT DEFAULT 'activity',
  title TEXT NOT NULL,
  airline TEXT,
  hotel_name TEXT,
  cost_usd REAL DEFAULT 0.00,
  cost_kzt REAL DEFAULT 0.00,
  details TEXT,
  item_time TEXT,
  FOREIGN KEY (day_id) REFERENCES trip_days (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id INTEGER NOT NULL,
  filename TEXT NOT NULL,
  caption TEXT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips (id) ON DELETE CASCADE
);

INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Казахстан', 'Қазақстан', 'KZ');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Россия', 'Ресей', 'RU');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Япония', 'Жапония', 'JP');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Австралия', 'Австралия', 'AU');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Китай', 'Қытай', 'CN');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Эфиопия', 'Эфиопия', 'ET');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Филиппины', 'Филиппиндер', 'PH');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Таиланд', 'Тайланд', 'TH');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Франция', 'Франция', 'FR');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Италия', 'Италия', 'IT');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('США', 'АҚШ', 'US');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Германия', 'Германия', 'DE');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Испания', 'Испания', 'ES');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Турция', 'Түркия', 'TR');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('ОАЭ', 'БАӘ', 'AE');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Великобритания', 'Ұлыбритания', 'GB');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Грузия', 'Грузия', 'GE');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Армения', 'Армения', 'AM');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Узбекистан', 'Өзбекстан', 'UZ');
INSERT INTO countries (name_ru, name_kk, iso_code) VALUES ('Египет', 'Мысыр', 'EG');
