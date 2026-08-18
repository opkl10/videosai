"""End to end analysis of real FFmpeg generated clips."""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from videosai import analyze_video
from videosai.errors import MediaError
from videosai.media import measure_audio, probe, scene_cuts

from .conftest import requires_ffmpeg

pytestmark = requires_ffmpeg


def codes(report) -> set[str]:
    return report.codes


@pytest.fixture(scope="module")
def good_report(clips):
    return analyze_video(clips["good"], platform="tiktok")


def test_probe_reads_streams(clips):
    info = probe(clips["good"])

    assert info.width == 1080 and info.height == 1920
    assert info.duration == pytest.approx(16.0, abs=0.3)
    assert info.fps == pytest.approx(30.0, abs=0.1)
    assert info.has_audio and info.audio_sample_rate == 48000
    assert info.is_vertical


def test_probe_rejects_missing_and_invalid_files(tmp_path: Path):
    with pytest.raises(MediaError):
        probe(tmp_path / "nope.mp4")

    broken = tmp_path / "broken.mp4"
    broken.write_bytes(b"not a video")
    with pytest.raises(MediaError):
        probe(broken)


def test_scene_cuts_find_the_generated_shot_changes(clips):
    cuts = scene_cuts(probe(clips["good"]))
    assert len(cuts) >= 5
    assert all(0 < cut < 16.5 for cut in cuts)


def test_audio_measurement_reports_loudness(clips):
    stats = measure_audio(probe(clips["good"]))

    assert stats is not None
    assert stats.integrated_lufs is not None
    assert -20 < stats.integrated_lufs < -8
    assert stats.loudness_source == "loudnorm"


def test_no_audio_stream_yields_no_stats(clips):
    assert measure_audio(probe(clips["dark_silent"])) is None


def test_well_formed_clip_scores_high(good_report):
    assert good_report.score >= 75
    assert good_report.grade in {"A", "B", "C"}
    assert not {"AUDIO_MISSING", "FRAMING_ASPECT", "FRAMING_LOW_RES", "DURATION_SHORT"} & codes(
        good_report
    )
    assert "DURATION_GOOD" in codes(good_report)
    assert len(good_report.media["cuts"]) >= 5


def test_every_category_is_scored_and_weighted(good_report):
    assert [c.key for c in good_report.categories] == [
        "hook",
        "pacing",
        "audio",
        "visual",
        "framing",
        "duration",
    ]
    assert sum(c.weight for c in good_report.categories) == pytest.approx(1.0)
    assert all(0 <= c.score <= 100 for c in good_report.categories)


def test_dark_silent_horizontal_clip_collects_the_expected_problems(clips):
    report = analyze_video(clips["dark_silent"], platform="tiktok")

    assert {
        "AUDIO_MISSING",
        "FRAMING_ASPECT",
        "FRAMING_LOW_RES",
        "VISUAL_UNDEREXPOSED",
        "PACING_FROZEN_SHOT",
    } <= codes(report)
    assert report.score < 65
    assert report.priorities()[0].penalty >= 25


def test_quiet_audio_is_detected_in_a_real_file(clips):
    report = analyze_video(clips["quiet"], platform="tiktok")
    assert "AUDIO_QUIET" in codes(report)


def test_dead_air_at_the_start_is_detected_in_a_real_file(clips):
    report = analyze_video(clips["dead_air"], platform="tiktok")

    finding = next(f for f in report.findings if f.code == "HOOK_DEAD_AIR")
    assert finding.params["lead_in"] > 1.0


def test_platform_changes_the_verdict_for_the_same_file(clips):
    vertical = analyze_video(clips["dark_silent"], platform="tiktok")
    horizontal = analyze_video(clips["dark_silent"], platform="youtube")

    assert "FRAMING_ASPECT" in codes(vertical)
    assert "FRAMING_ASPECT" not in codes(horizontal)


def test_thumbnails_are_exported_and_spread_out(clips, tmp_path: Path):
    report = analyze_video(clips["good"], platform="tiktok", thumbnails_dir=tmp_path)

    assert len(report.thumbnail_candidates) == 3
    times = sorted(c.time for c in report.thumbnail_candidates)
    assert all(later - earlier >= 1.0 for earlier, later in zip(times, times[1:]))
    for candidate in report.thumbnail_candidates:
        assert candidate.file is not None
        assert Path(candidate.file).stat().st_size > 0


def test_report_serializes_to_json(good_report):
    payload = json.loads(json.dumps(good_report.as_dict(), ensure_ascii=False))

    assert payload["file"].endswith("good.mp4")
    assert payload["media"]["audio"]["loudness_source"] == "loudnorm"
    assert payload["version"]
