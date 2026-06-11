# Personal AI Auto Poster — Phase 2

ระบบส่วนตัวสำหรับโพสต์คลิปอัตโนมัติ TikTok / Instagram

Phase 2 เพิ่มระบบอัปโหลดวิดีโอและอ่าน metadata ด้วย `ffprobe` แล้วบันทึกลง SQLite

## ฟีเจอร์ใน Phase 2

- หน้า Dashboard
- หน้า Health Check
- หน้า Upload Video
- หน้า Media Library
- ตรวจชนิดไฟล์ด้วย PHP `fileinfo`
- ย้ายไฟล์อัปโหลดด้วย `move_uploaded_file()`
- อ่านวิดีโอด้วย `ffprobe`
- เก็บข้อมูลลง `media_assets`
- Preview วิดีโอจากไฟล์ local

## ต้องมีในเครื่อง

```powershell
C:\php83\php.exe -v
C:\php83\php.exe -m | findstr /i "fileinfo pdo_sqlite sqlite3 curl mbstring openssl"
ffmpeg -version
ffprobe -version
```

## วิธีรันเร็วสุดบน Windows

```powershell
cd C:\Users\ADMIN\Downloads\personal-ai-auto-poster-phase2\personal-ai-auto-poster-phase2
copy .env.example .env
C:\php83\php.exe bin\migrate.php
C:\php83\php.exe -S 127.0.0.1:8080 -t public
```

เปิดเว็บ:

```text
http://127.0.0.1:8080
```

อัปโหลดวิดีโอ:

```text
http://127.0.0.1:8080/upload.php
```

ดูวิดีโอที่อัปโหลดแล้ว:

```text
http://127.0.0.1:8080/media.php
```

## เพิ่มขนาดอัปโหลดวิดีโอ

เปิด:

```text
C:\php83\php.ini
```

แก้หรือเพิ่มค่าเหล่านี้:

```ini
upload_max_filesize = 500M
post_max_size = 520M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

แล้ว restart PHP server:

```powershell
Ctrl + C
C:\php83\php.exe -S 127.0.0.1:8080 -t public
```

## Flow ของ Phase 2

```text
Browser upload
↓
PHP $_FILES
↓
fileinfo ตรวจ MIME
↓
move_uploaded_file ไป storage/input/YYYY-MM
↓
ffprobe อ่าน duration / width / height / codec
↓
INSERT ลง SQLite media_assets
↓
แสดงใน Dashboard / Media Library
```

## ขั้นต่อไป

Phase 3: OpenAI Caption Engine

```text
เลือกวิดีโอ
↓
ใส่หัวข้อ/โทน
↓
OpenAI สร้าง caption
↓
บันทึกลง captions
```
