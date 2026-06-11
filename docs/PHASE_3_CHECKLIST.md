# Phase 3 – AI Caption Generator Checklist

- [ ] Add `app/OpenAIClient.php` to call the OpenAI Responses API.
- [ ] Add `app/CaptionRepository.php` for caption storage and retrieval.
- [ ] Extend the `captions` table with video linkage, topic, tone, target audience, prompt text and raw response JSON.
- [ ] Add indexes for `captions.media_asset_id` and `captions.created_at`.
- [ ] Add `public/caption.php` for generating captions from a selected video.
- [ ] Add `public/captions.php` for viewing recent captions.
- [ ] Add Generate Caption buttons to the dashboard and media library.
- [ ] Add captions table and OpenAI config checks to `public/health.php`.
- [ ] Update `README.md` with Phase 3 setup and usage.
- [ ] Keep `.env`, SQLite databases and uploaded videos out of Git.

## Manual test

1. Run `C:\php83\php.exe bin\migrate.php`.
2. Start the server with `C:\php83\php.exe -S 127.0.0.1:8080 -t public`.
3. Open `http://127.0.0.1:8080/media.php?v=3`.
4. Choose a video and click `สร้างแคปชั่น`.
5. Without an API key, the page must show a clear warning and must not fatal.
6. With `OPENAI_API_KEY` and `OPENAI_MODEL` set, the caption must be saved and visible in `captions.php`.
