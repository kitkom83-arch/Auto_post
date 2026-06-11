# Phase 2 Checklist — Upload Video + ffprobe Metadata

## เป้าหมาย

อัปโหลดไฟล์วิดีโอผ่านหน้าเว็บ แล้วระบบต้องทำสิ่งนี้ได้:

1. ตรวจ upload error จาก PHP
2. ตรวจ extension เบื้องต้น: `mp4`, `mov`, `m4v`, `webm`
3. ตรวจ MIME ด้วย `fileinfo`
4. ย้ายไฟล์ด้วย `move_uploaded_file()` ไปที่ `storage/input/YYYY-MM/`
5. อ่าน metadata ด้วย `ffprobe`
6. บันทึกข้อมูลลงตาราง `media_assets`
7. แสดงรายการวิดีโอในหน้า Dashboard และ Media Library

## คำสั่งที่ต้องรันหลังแตก ZIP

```powershell
cd C:\Users\ADMIN\Downloads\personal-ai-auto-poster-phase2\personal-ai-auto-poster-phase2
C:\php83\php.exe bin\migrate.php
C:\php83\php.exe -S 127.0.0.1:8080 -t public
```

เปิด:

```text
http://127.0.0.1:8080/upload.php
```

## ถ้าอัปโหลดคลิปใหญ่ไม่ได้

เปิดไฟล์:

```text
C:\php83\php.ini
```

ค้นหาและแก้:

```ini
upload_max_filesize = 500M
post_max_size = 520M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

จากนั้นปิด server ด้วย `Ctrl + C` แล้วเปิดใหม่:

```powershell
C:\php83\php.exe -S 127.0.0.1:8080 -t public
```

## เช็กเร็ว

```powershell
ffmpeg -version
ffprobe -version
C:\php83\php.exe -m | findstr /i "fileinfo pdo_sqlite sqlite3 curl"
```

## ผลลัพธ์ที่ต้องเห็น

หลังอัปโหลดสำเร็จ หน้า Media Library ต้องแสดง:

- ชื่อไฟล์เดิม
- MIME type
- ขนาดไฟล์
- ความยาววิดีโอ
- ความละเอียด
- video codec
- audio codec
- SHA256

## สิ่งที่ยังไม่ทำใน Phase 2

- ยังไม่เรียก OpenAI
- ยังไม่ใส่เพลง
- ยังไม่อัปโหลด R2
- ยังไม่โพสต์ Zernio
- ยังไม่ตั้ง schedule
