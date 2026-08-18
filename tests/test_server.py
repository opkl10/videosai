"""The upload server."""

from __future__ import annotations

from collections.abc import Iterator
from pathlib import Path

import pytest

from .conftest import requires_ffmpeg

pytest.importorskip("fastapi", reason="the server extra is not installed")

from fastapi.testclient import TestClient

from videosai.server import ServerSettings, _safe_stem, create_app, upload_page


@pytest.fixture
def settings(tmp_path: Path) -> ServerSettings:
    return ServerSettings(workdir=tmp_path / "runs")


@pytest.fixture
def client(settings: ServerSettings) -> Iterator[TestClient]:
    with TestClient(create_app(settings)) as test_client:
        yield test_client


def upload(client: TestClient, path: Path, url: str = "/api/analyze", **data):
    with path.open("rb") as handle:
        return client.post(url, files={"file": (path.name, handle, "video/mp4")}, data=data)


# ------------------------------------------------------------------- pages
def test_upload_page_is_served_in_hebrew_by_default(client: TestClient):
    response = client.get("/")

    assert response.status_code == 200
    assert 'dir="rtl"' in response.text
    assert 'action="/analyze"' in response.text
    assert 'name="file"' in response.text
    assert 'value="tiktok" selected' in response.text


def test_upload_page_can_be_rendered_in_english(client: TestClient):
    response = client.get("/?lang=en")

    assert 'dir="ltr"' in response.text
    assert "Upload a video" in response.text


def test_upload_page_escapes_error_text():
    page = upload_page("en", error='<script>alert("x")</script>')

    assert "<script>alert" not in page
    assert "&lt;script&gt;" in page


def test_healthz_reports_ffmpeg_availability(client: TestClient):
    payload = client.get("/healthz").json()

    assert payload["status"] in {"ok", "degraded"}
    assert payload["version"]
    assert isinstance(payload["ffmpeg"], bool)


def test_platform_presets_are_exposed_as_json(client: TestClient):
    payload = client.get("/api/platforms?lang=en").json()
    tiktok = next(item for item in payload if item["key"] == "tiktok")

    assert tiktok["label"] == "TikTok"
    assert tiktok["ideal_duration"] == [15, 60]
    assert tiktok["loudness_target"] == -14.0


# -------------------------------------------------------------- validation
def test_a_missing_file_field_is_rejected(client: TestClient):
    assert client.post("/api/analyze").status_code == 422


def test_a_non_video_extension_is_rejected(client: TestClient):
    response = client.post(
        "/api/analyze", files={"file": ("notes.txt", b"hello", "text/plain")}
    )

    assert response.status_code == 400
    assert "not a supported video file" in response.json()["detail"]


def test_an_unknown_platform_is_rejected(client: TestClient):
    response = client.post(
        "/api/analyze",
        files={"file": ("clip.mp4", b"data", "video/mp4")},
        data={"platform": "myspace"},
    )

    assert response.status_code == 400
    assert "Unknown platform" in response.json()["detail"]


def test_an_empty_file_is_rejected(client: TestClient):
    response = client.post("/api/analyze", files={"file": ("clip.mp4", b"", "video/mp4")})

    assert response.status_code == 400
    assert "empty" in response.json()["detail"]


def test_a_file_that_is_not_really_a_video_gets_a_clear_error(client: TestClient):
    response = client.post(
        "/api/analyze", files={"file": ("clip.mp4", b"not a video at all", "video/mp4")}
    )

    assert response.status_code == 400
    assert "Could not read 'clip.mp4'" in response.json()["detail"]


def test_uploads_over_the_limit_are_rejected(tmp_path: Path):
    settings = ServerSettings(workdir=tmp_path / "runs", max_upload_bytes=1024)
    with TestClient(create_app(settings)) as client:
        response = client.post(
            "/api/analyze", files={"file": ("clip.mp4", b"x" * 4096, "video/mp4")}
        )

    assert response.status_code == 413
    assert "larger than" in response.json()["detail"]


def test_rejected_uploads_leave_nothing_behind(client: TestClient, settings: ServerSettings):
    client.post("/api/analyze", files={"file": ("clip.mp4", b"not a video", "video/mp4")})
    assert list(settings.workdir.glob("*")) == []


def test_safe_stem_strips_paths_and_odd_characters():
    assert _safe_stem("../../etc/passwd") == "passwd"
    assert _safe_stem("my clip (final).mp4") == "my-clip--final"
    assert _safe_stem("") == "video"


# ------------------------------------------------------------- happy paths
@requires_ffmpeg
@pytest.mark.integration
def test_json_api_returns_a_scored_report(client: TestClient, clips):
    payload = upload(client, clips["good"], platform="tiktok").json()

    assert 0 <= payload["score"] <= 100
    assert payload["file"] == "good.mp4"  # the server path stays hidden
    assert payload["categories"][0]["key"] == "hook"
    # Cover timestamps always come back; exporting the images is opt-in.
    assert payload["thumbnail_candidates"]
    assert all(candidate["file"] is None for candidate in payload["thumbnail_candidates"])


@requires_ffmpeg
@pytest.mark.integration
def test_thumbnails_are_served_over_http(client: TestClient, clips):
    payload = upload(client, clips["good"], thumbnails="true").json()
    urls = [candidate["file"] for candidate in payload["thumbnail_candidates"]]

    assert len(urls) == 3
    assert all(url.startswith("/runs/") and "good-cover-" in url for url in urls)
    image = client.get(urls[0])
    assert image.status_code == 200
    assert image.headers["content-type"] == "image/jpeg"


@requires_ffmpeg
@pytest.mark.integration
def test_form_upload_returns_the_html_report(client: TestClient, clips):
    response = upload(client, clips["good"], url="/analyze", lang="he", thumbnails="on")

    assert response.status_code == 200
    assert response.text.startswith("<!DOCTYPE html>")
    assert "דוח ניתוח וידאו" in response.text
    assert "נתח סרטון נוסף" in response.text  # link back to the upload page
    assert response.text.count("<img") == 3


@requires_ffmpeg
@pytest.mark.integration
def test_form_upload_reports_errors_on_the_upload_page(client: TestClient):
    response = client.post(
        "/analyze", files={"file": ("notes.txt", b"hello", "text/plain")}, data={"lang": "en"}
    )

    assert response.status_code == 400
    assert 'class="error"' in response.text
    assert "not a supported video file" in response.text


@requires_ffmpeg
@pytest.mark.integration
def test_the_uploaded_video_is_deleted_after_the_analysis(
    client: TestClient, clips, settings: ServerSettings
):
    upload(client, clips["good"], thumbnails="true")

    run_dirs = list(settings.workdir.glob("*"))
    assert len(run_dirs) == 1
    assert list(run_dirs[0].glob("*.mp4")) == []
    assert len(list((run_dirs[0] / "covers").glob("*.jpg"))) == 3


@requires_ffmpeg
@pytest.mark.integration
def test_uploads_can_be_kept_for_debugging(tmp_path: Path, clips):
    settings = ServerSettings(workdir=tmp_path / "runs", keep_uploads=True)
    with TestClient(create_app(settings)) as client:
        upload(client, clips["good"])

    run_dir = next(iter(settings.workdir.glob("*")))
    assert list(run_dir.glob("*.mp4"))


@requires_ffmpeg
@pytest.mark.integration
def test_old_runs_are_swept_on_the_next_request(client: TestClient, clips, settings: ServerSettings):
    stale = settings.workdir / "stale"
    stale.mkdir(parents=True)
    (stale / "leftover.jpg").write_bytes(b"x")
    import os

    os.utime(stale, (0, 0))

    upload(client, clips["good"])
    assert not stale.exists()
