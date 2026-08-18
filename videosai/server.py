"""HTTP server: upload a video in the browser, get the report back.

Requires the optional extra: ``pip install "videosai[server]"``.

    videosai serve --host 0.0.0.0 --port 8000

Routes:
    GET  /                upload page
    POST /analyze         multipart upload, responds with the HTML report
    POST /api/analyze     multipart upload, responds with the JSON report
    GET  /api/platforms   the platform presets
    GET  /healthz         liveness plus whether FFmpeg is reachable
"""

from __future__ import annotations

import asyncio
import html
import shutil
import time
import uuid
from dataclasses import dataclass, field, replace
from pathlib import Path
from tempfile import gettempdir
from typing import Annotated

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from starlette.concurrency import run_in_threadpool

from . import messages
from .engine import analyze_video
from .errors import FFmpegMissingError, MediaError
from .media import require_ffmpeg
from .models import Report
from .platforms import PLATFORMS
from .render import to_html
from .version import __version__

CHUNK_SIZE = 1 << 20
VIDEO_SUFFIXES = frozenset({".mp4", ".mov", ".m4v", ".webm", ".mkv", ".avi", ".mpg", ".mpeg"})


@dataclass
class ServerSettings:
    """Runtime limits for the upload service."""

    workdir: Path = field(default_factory=lambda: Path(gettempdir()) / "videosai-runs")
    max_upload_bytes: int = 512 * 1024 * 1024
    #: Uploads and exported frames older than this are swept on the next request.
    run_ttl_seconds: float = 3600.0
    #: Analysis is CPU and FFmpeg bound, so only a few run at a time.
    max_concurrent_analyses: int = 2
    #: Keeping the uploaded video is off by default; only frames are served.
    keep_uploads: bool = False


def create_app(settings: ServerSettings | None = None) -> FastAPI:
    settings = settings or ServerSettings()
    runs = settings.workdir
    runs.mkdir(parents=True, exist_ok=True)
    limiter = asyncio.Semaphore(settings.max_concurrent_analyses)

    app = FastAPI(
        title="videosai",
        version=__version__,
        description="Analyze a video and get what works, what does not, and what to fix first.",
    )
    app.state.settings = settings
    app.mount("/runs", StaticFiles(directory=runs), name="runs")

    @app.get("/", response_class=HTMLResponse)
    async def home(lang: str = messages.DEFAULT_LANGUAGE) -> HTMLResponse:
        return HTMLResponse(upload_page(messages.normalize_language(lang)))

    @app.get("/healthz")
    async def healthz() -> dict[str, object]:
        try:
            require_ffmpeg()
            ffmpeg_ready = True
        except FFmpegMissingError:
            ffmpeg_ready = False
        return {"status": "ok" if ffmpeg_ready else "degraded", "version": __version__, "ffmpeg": ffmpeg_ready}

    @app.get("/api/platforms")
    async def api_platforms(lang: str = messages.DEFAULT_LANGUAGE) -> list[dict[str, object]]:
        lang = messages.normalize_language(lang)
        return [
            {
                "key": platform.key,
                "label": platform.label(lang),
                "target_aspect": round(platform.target_aspect, 4) or None,
                "min_height": platform.min_height,
                "ideal_duration": list(platform.ideal_duration),
                "max_duration": platform.max_duration,
                "loudness_target": platform.loudness_target,
            }
            for platform in PLATFORMS.values()
        ]

    @app.post("/analyze", response_class=HTMLResponse)
    async def analyze_form(
        file: Annotated[UploadFile, File()],
        platform: Annotated[str, Form()] = "tiktok",
        lang: Annotated[str, Form()] = messages.DEFAULT_LANGUAGE,
        thumbnails: Annotated[str | None, Form()] = None,
    ) -> HTMLResponse:
        lang = messages.normalize_language(lang)
        try:
            report = await _analyze_upload(
                file, platform=platform, with_thumbnails=thumbnails is not None,
                settings=settings, limiter=limiter,
            )
        except HTTPException as error:
            return HTMLResponse(
                upload_page(lang, error=str(error.detail)), status_code=error.status_code
            )
        return HTMLResponse(to_html(report, lang=lang, back_link=f"/?lang={lang}"))

    @app.post("/api/analyze")
    async def analyze_api(
        file: Annotated[UploadFile, File()],
        platform: Annotated[str, Form()] = "tiktok",
        thumbnails: Annotated[bool, Form()] = False,
    ) -> JSONResponse:
        report = await _analyze_upload(
            file, platform=platform, with_thumbnails=thumbnails, settings=settings, limiter=limiter
        )
        return JSONResponse(report.as_dict())

    @app.get("/docs-redirect", include_in_schema=False)
    async def docs_redirect() -> RedirectResponse:
        return RedirectResponse("/docs")

    return app


async def _analyze_upload(
    upload: UploadFile,
    *,
    platform: str,
    with_thumbnails: bool,
    settings: ServerSettings,
    limiter: asyncio.Semaphore,
) -> Report:
    if platform not in PLATFORMS:
        raise HTTPException(400, f"Unknown platform '{platform}'.")

    filename = Path(upload.filename or "upload.mp4").name
    suffix = Path(filename).suffix.lower()
    if suffix not in VIDEO_SUFFIXES:
        allowed = ", ".join(sorted(VIDEO_SUFFIXES))
        raise HTTPException(400, f"'{filename}' is not a supported video file. Allowed: {allowed}")

    _sweep_old_runs(settings.workdir, settings.run_ttl_seconds)
    run_id = uuid.uuid4().hex[:12]
    run_dir = settings.workdir / run_id
    covers = run_dir / "covers"
    run_dir.mkdir(parents=True, exist_ok=True)
    source = run_dir / f"{_safe_stem(filename)}{suffix}"

    try:
        await _save_upload(upload, source, settings.max_upload_bytes)
        async with limiter:
            report = await run_in_threadpool(
                analyze_video,
                source,
                platform=platform,
                thumbnails_dir=covers if with_thumbnails else None,
            )
    except HTTPException:
        shutil.rmtree(run_dir, ignore_errors=True)
        raise
    except FFmpegMissingError as error:
        shutil.rmtree(run_dir, ignore_errors=True)
        raise HTTPException(503, str(error)) from error
    except MediaError as error:
        shutil.rmtree(run_dir, ignore_errors=True)
        raise HTTPException(400, f"Could not read '{filename}': {error}") from error

    if not settings.keep_uploads:
        source.unlink(missing_ok=True)

    # The report is shown in a browser, so exported frames need URLs, not paths.
    report.file = filename
    report.thumbnail_candidates = [
        replace(candidate, file=f"/runs/{run_id}/covers/{Path(candidate.file).name}")
        if candidate.file
        else candidate
        for candidate in report.thumbnail_candidates
    ]
    return report


def _safe_stem(filename: str, limit: int = 40) -> str:
    """A file name safe to write to disk and to expose in a URL."""
    stem = Path(filename).stem
    cleaned = "".join(char if char.isalnum() or char in "-_" else "-" for char in stem)
    cleaned = cleaned.strip("-")[:limit]
    return cleaned or "video"


async def _save_upload(upload: UploadFile, destination: Path, limit: int) -> int:
    """Stream the upload to disk, rejecting anything over ``limit`` bytes."""
    size = 0
    with destination.open("wb") as target:
        while chunk := await upload.read(CHUNK_SIZE):
            size += len(chunk)
            if size > limit:
                raise HTTPException(413, f"The file is larger than {limit // (1024 * 1024)} MB.")
            target.write(chunk)
    if size == 0:
        raise HTTPException(400, "The uploaded file is empty.")
    return size


def _sweep_old_runs(workdir: Path, ttl_seconds: float) -> None:
    cutoff = time.time() - ttl_seconds
    for directory in workdir.glob("*"):
        try:
            if directory.is_dir() and directory.stat().st_mtime < cutoff:
                shutil.rmtree(directory, ignore_errors=True)
        except OSError:
            continue


_PAGE_TEXT = {
    "he": {
        "title": "videosai",
        "tagline": "העלה סרטון וקבל דוח: מה עובד, מה לא, ומה לתקן קודם.",
        "file": "קובץ הסרטון",
        "file_hint": "mp4, mov, webm, mkv ועוד",
        "platform": "פלטפורמת יעד",
        "lang": "שפת הדוח",
        "thumbnails": "הצע פריימים לתמונה ראשית",
        "submit": "נתח את הסרטון",
        "working": "מנתח... הניתוח מקומי ולוקח בין שניות לדקה, תלוי באורך הסרטון.",
        "note": "הקובץ נשאר על השרת הזה ונמחק אחרי הניתוח. אין העלאה לשירות חיצוני.",
        "api": "יש גם API ב-JSON",
        "he": "עברית",
        "en": "אנגלית",
    },
    "en": {
        "title": "videosai",
        "tagline": "Upload a video and get a report: what works, what does not, what to fix first.",
        "file": "Video file",
        "file_hint": "mp4, mov, webm, mkv and more",
        "platform": "Target platform",
        "lang": "Report language",
        "thumbnails": "Suggest cover frames",
        "submit": "Analyze the video",
        "working": "Analyzing... this runs locally and takes seconds to a minute, depending on length.",
        "note": "The file stays on this server and is deleted after the analysis. Nothing is uploaded elsewhere.",
        "api": "A JSON API is available too",
        "he": "Hebrew",
        "en": "English",
    },
}

_UPLOAD_TEMPLATE = """<!DOCTYPE html>
<html lang="{lang}" dir="{direction}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<style>
:root {{ color-scheme: dark; }}
* {{ box-sizing: border-box; }}
body {{ margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 32px 20px;
  font-family: -apple-system, "Segoe UI", Rubik, Arial, sans-serif; background: #0f1115;
  color: #e9edf5; line-height: 1.6; }}
.card {{ width: 100%; max-width: 560px; background: #171a21; border: 1px solid #23283a;
  border-radius: 18px; padding: 28px; }}
h1 {{ margin: 0 0 6px; font-size: 28px; }}
.tagline {{ color: #99a2b3; margin: 0 0 24px; }}
label {{ display: block; font-size: 14px; color: #c3cad8; margin-bottom: 6px; }}
.field {{ margin-bottom: 18px; }}
.hint {{ font-size: 12px; color: #6f7787; margin-top: 4px; }}
input[type=file], select {{ width: 100%; padding: 11px 12px; background: #1d212b; color: #e9edf5;
  border: 1px solid #2b3140; border-radius: 10px; font-size: 14px; font-family: inherit; }}
input[type=file]::file-selector-button {{ background: #2b3140; color: #e9edf5; border: 0;
  border-radius: 8px; padding: 7px 12px; margin-inline-end: 12px; cursor: pointer; }}
.row {{ display: flex; gap: 14px; }}
.row > * {{ flex: 1; }}
.check {{ display: flex; align-items: center; gap: 9px; font-size: 14px; color: #c3cad8; }}
.check input {{ width: 17px; height: 17px; accent-color: #30a46c; }}
button {{ width: 100%; margin-top: 8px; padding: 13px; font-size: 16px; font-weight: 600;
  font-family: inherit; color: #06231a; background: #30a46c; border: 0; border-radius: 10px;
  cursor: pointer; }}
button:disabled {{ background: #2b3140; color: #99a2b3; cursor: progress; }}
.error {{ background: #3a1d20; border: 1px solid #e5484d; color: #ffc9cb; border-radius: 10px;
  padding: 11px 14px; margin-bottom: 20px; font-size: 14px; }}
.working {{ display: none; margin-top: 14px; font-size: 14px; color: #f5a524; }}
.working.on {{ display: block; }}
.note {{ margin-top: 22px; font-size: 12px; color: #6f7787; }}
a {{ color: #7cc4ff; }}
</style>
</head>
<body>
<form class="card" method="post" action="/analyze" enctype="multipart/form-data" id="form">
  <h1>{title}</h1>
  <p class="tagline">{tagline}</p>
  {error}
  <div class="field">
    <label for="file">{file_label}</label>
    <input id="file" type="file" name="file" accept="video/*" required>
    <div class="hint">{file_hint}</div>
  </div>
  <div class="row">
    <div class="field">
      <label for="platform">{platform_label}</label>
      <select id="platform" name="platform">{platform_options}</select>
    </div>
    <div class="field">
      <label for="lang">{lang_label}</label>
      <select id="lang" name="lang">{lang_options}</select>
    </div>
  </div>
  <div class="field check">
    <input id="thumbnails" type="checkbox" name="thumbnails" checked>
    <label for="thumbnails" style="margin:0">{thumbnails_label}</label>
  </div>
  <button type="submit" id="submit">{submit}</button>
  <div class="working" id="working">{working}</div>
  <p class="note">{note} · <a href="/docs">{api}</a></p>
</form>
<script>
document.getElementById('form').addEventListener('submit', function () {{
  document.getElementById('submit').disabled = true;
  document.getElementById('working').classList.add('on');
}});
</script>
</body>
</html>
"""


def upload_page(lang: str = messages.DEFAULT_LANGUAGE, error: str | None = None) -> str:
    """The upload form, in Hebrew or English."""
    lang = messages.normalize_language(lang)
    text = _PAGE_TEXT[lang]

    platform_options = "".join(
        f'<option value="{platform.key}"{" selected" if platform.key == "tiktok" else ""}>'
        f"{html.escape(platform.label(lang))}</option>"
        for platform in PLATFORMS.values()
    )
    lang_options = "".join(
        f'<option value="{code}"{" selected" if code == lang else ""}>'
        f"{html.escape(text[code])}</option>"
        for code in messages.LANGUAGES
    )

    return _UPLOAD_TEMPLATE.format(
        lang=lang,
        direction="rtl" if lang == "he" else "ltr",
        title=html.escape(text["title"]),
        tagline=html.escape(text["tagline"]),
        error=f'<div class="error">{html.escape(error)}</div>' if error else "",
        file_label=html.escape(text["file"]),
        file_hint=html.escape(text["file_hint"]),
        platform_label=html.escape(text["platform"]),
        platform_options=platform_options,
        lang_label=html.escape(text["lang"]),
        lang_options=lang_options,
        thumbnails_label=html.escape(text["thumbnails"]),
        submit=html.escape(text["submit"]),
        working=html.escape(text["working"]),
        note=html.escape(text["note"]),
        api=html.escape(text["api"]),
    )


def serve(
    host: str = "127.0.0.1",
    port: int = 8000,
    settings: ServerSettings | None = None,
    log_level: str = "info",
) -> None:
    """Run the server with uvicorn."""
    import uvicorn

    uvicorn.run(create_app(settings), host=host, port=port, log_level=log_level)
