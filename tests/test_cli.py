"""Command line behaviour."""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from videosai.cli import EXIT_BELOW_THRESHOLD, EXIT_ERROR, EXIT_OK, main

from .conftest import requires_ffmpeg


def test_platforms_command_lists_presets(capsys: pytest.CaptureFixture[str]):
    assert main(["platforms"]) == EXIT_OK

    output = capsys.readouterr().out
    assert "tiktok" in output
    assert "youtube" in output


def test_no_command_prints_help(capsys: pytest.CaptureFixture[str]):
    assert main([]) == EXIT_ERROR
    assert "usage" in capsys.readouterr().out


def test_missing_file_reports_an_error(capsys: pytest.CaptureFixture[str], tmp_path: Path):
    assert main(["analyze", str(tmp_path / "nope.mp4")]) == EXIT_ERROR
    assert "nope.mp4" in capsys.readouterr().err


def test_out_rejects_multiple_files(capsys: pytest.CaptureFixture[str], tmp_path: Path):
    exit_code = main(["analyze", "a.mp4", "b.mp4", "--out", str(tmp_path / "r.md")])

    assert exit_code == EXIT_ERROR
    assert "single file" in capsys.readouterr().err


@requires_ffmpeg
def test_text_report_is_printed(clips, capsys: pytest.CaptureFixture[str]):
    assert main(["analyze", str(clips["good"]), "--platform", "tiktok"]) == EXIT_OK

    output = capsys.readouterr().out
    assert "/100" in output
    assert "טיקטוק" in output


@requires_ffmpeg
def test_json_format_is_machine_readable(clips, capsys: pytest.CaptureFixture[str]):
    assert main(["analyze", str(clips["good"]), "-f", "json"]) == EXIT_OK

    payload = json.loads(capsys.readouterr().out)
    assert payload["platform"] == "tiktok"
    assert payload["categories"]


@requires_ffmpeg
def test_english_markdown_is_written_to_a_file(clips, tmp_path: Path):
    destination = tmp_path / "nested" / "report.md"
    exit_code = main(
        ["analyze", str(clips["good"]), "-f", "md", "-l", "en", "-o", str(destination)]
    )

    assert exit_code == EXIT_OK
    text = destination.read_text(encoding="utf-8")
    assert text.startswith("# Video analysis report")
    assert "Scores by category" in text


@requires_ffmpeg
def test_html_report_embeds_exported_thumbnails(clips, tmp_path: Path):
    destination = tmp_path / "report.html"
    exit_code = main(
        [
            "analyze",
            str(clips["good"]),
            "-f",
            "html",
            "-o",
            str(destination),
            "--thumbnails",
            str(tmp_path / "covers"),
            "--thumbnail-count",
            "2",
        ]
    )

    assert exit_code == EXIT_OK
    html = destination.read_text(encoding="utf-8")
    assert html.count("<img") == 2
    assert len(list((tmp_path / "covers").glob("*.jpg"))) == 2


@requires_ffmpeg
def test_fail_under_flags_a_weak_video(clips, capsys: pytest.CaptureFixture[str]):
    assert main(["analyze", str(clips["dark_silent"]), "--fail-under", "70"]) == EXIT_BELOW_THRESHOLD
    assert main(["analyze", str(clips["good"]), "--fail-under", "70"]) == EXIT_OK
    capsys.readouterr()


@requires_ffmpeg
def test_multiple_files_are_analyzed_in_one_run(clips, capsys: pytest.CaptureFixture[str]):
    exit_code = main(["analyze", str(clips["good"]), str(clips["quiet"]), "-f", "text"])

    assert exit_code == EXIT_OK
    assert capsys.readouterr().out.count("דוח ניתוח וידאו") == 2
