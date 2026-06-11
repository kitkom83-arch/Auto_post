# Phase 1 Checklist

ใช้เช็กเร็วหลังติดตั้ง

```bash
cp .env.example .env
php bin/migrate.php
php workers/loop.php
php -S 127.0.0.1:8080 -t public
```

เปิด:

```text
http://127.0.0.1:8080/health.php
```

ต้องเห็น:

- PHP version = PASS
- PHP extension pdo_sqlite = PASS
- SQLite connection = PASS
- SQLite WAL mode = PASS
- Database tables = PASS
- ffmpeg = PASS
- ffprobe = PASS
- Worker heartbeat file = PASS หลังรัน worker

ถ้า pdo_sqlite FAIL:

```bash
sudo apt install php8.3-sqlite3
sudo systemctl restart php8.3-fpm
```

ถ้า ffmpeg FAIL:

```bash
sudo apt install ffmpeg
```
