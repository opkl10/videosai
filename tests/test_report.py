"""Messages, scoring and the report renderers."""

from __future__ import annotations

import json

import pytest

from videosai import messages
from videosai.messages import CATALOG, format_time
from videosai.models import (
    CategoryResult,
    Finding,
    Report,
    Severity,
    ThumbnailCandidate,
    grade_for,
)
from videosai.platforms import PLATFORMS, get_platform
from videosai.render import to_html, to_json, to_markdown, to_text
from videosai.scoring import apply_weights, overall_score


def sample_report() -> Report:
    hook = CategoryResult(
        key="hook",
        score=62.0,
        metrics={"cuts_in_hook": 0, "hook_motion": 0.4},
        findings=[
            Finding(
                code="HOOK_DEAD_AIR",
                severity=Severity.CRITICAL,
                penalty=25.0,
                params={"lead_in": 1.4},
                at=0.0,
            ),
            Finding(code="HOOK_NO_CUT", severity=Severity.INFO, penalty=8.0, at=0.0),
        ],
    )
    audio = CategoryResult(
        key="audio",
        score=100.0,
        metrics={"integrated_lufs": -14.2},
        findings=[Finding(code="AUDIO_GOOD", severity=Severity.GOOD, params={"lufs": -14.2})],
    )
    duration = CategoryResult(
        key="duration",
        score=85.0,
        metrics={"duration": 12.0},
        findings=[
            Finding(
                code="DURATION_SHORT",
                severity=Severity.WARNING,
                penalty=15.0,
                params={"duration": 12.0, "min_d": 15, "max_d": 60, "platform": "tiktok"},
            )
        ],
    )
    categories = [hook, audio, duration]
    apply_weights(categories, get_platform("tiktok").weights)
    return Report(
        file="/clips/demo.mp4",
        platform="tiktok",
        score=overall_score(categories),
        media={"duration": 12.0, "width": 1080, "height": 1920, "fps": 30.0, "has_audio": True},
        categories=categories,
        thumbnail_candidates=[ThumbnailCandidate(time=3.5, score=78.0, sharpness=300, brightness=128, saturation=0.3)],
        version="0.1.0",
    )


# --------------------------------------------------------------- messages
@pytest.mark.parametrize("code", sorted(CATALOG))
def test_every_message_exists_in_both_languages(code: str):
    entry = CATALOG[code]
    assert set(entry) == {"he", "en"}
    for language, (title, detail, _fix) in entry.items():
        assert title.strip(), f"{code}/{language} has no title"
        assert detail.strip(), f"{code}/{language} has no detail"


def test_missing_parameters_do_not_break_rendering():
    text = messages.render(Finding(code="AUDIO_QUIET", severity=Severity.WARNING), "he")
    assert "{" in text.detail  # the template is returned as-is instead of raising


def test_unknown_code_falls_back_to_the_code_itself():
    text = messages.render(Finding(code="NOPE", severity=Severity.INFO), "en")
    assert text.title == "NOPE"


def test_platform_placeholder_is_localized():
    finding = Finding(
        code="DURATION_GOOD",
        severity=Severity.GOOD,
        params={"duration": 20.0, "platform": "tiktok"},
    )
    assert "טיקטוק" in messages.render(finding, "he").detail
    assert "TikTok" in messages.render(finding, "en").detail


def test_language_normalization_and_fallback():
    assert messages.normalize_language("HE") == "he"
    assert messages.normalize_language("en-US") == "en"
    assert messages.normalize_language("fr") == "he"
    assert messages.normalize_language(None) == "he"


def test_format_time():
    assert format_time(0) == "0:00.0"
    assert format_time(75.5) == "1:15.5"
    assert format_time(None) == "-"


# ---------------------------------------------------------------- scoring
def test_weights_are_normalized_to_one():
    categories = [CategoryResult(key="hook", score=50), CategoryResult(key="audio", score=100)]
    apply_weights(categories, {"hook": 3, "audio": 1})

    assert sum(c.weight for c in categories) == pytest.approx(1.0)
    assert overall_score(categories) == pytest.approx(62.5)


def test_missing_weights_fall_back_to_equal_weighting():
    categories = [CategoryResult(key="a", score=40), CategoryResult(key="b", score=80)]
    apply_weights(categories, {})
    assert overall_score(categories) == pytest.approx(60.0)


def test_every_platform_weights_all_analyzer_categories():
    from videosai.analyzers import ANALYZERS

    for platform in PLATFORMS.values():
        assert set(platform.weights) == set(ANALYZERS), platform.key


def test_grade_bands():
    assert grade_for(95) == "A"
    assert grade_for(80) == "B"
    assert grade_for(61) == "D"
    assert grade_for(10) == "F"


def test_platform_lookup_accepts_aliases_and_rejects_junk():
    assert get_platform("IG").key == "reels"
    assert get_platform("youtube_shorts").key == "shorts"
    with pytest.raises(KeyError):
        get_platform("myspace")


# ----------------------------------------------------------------- report
def test_report_orders_issues_by_severity_then_impact():
    report = sample_report()
    issues = report.issues()

    assert [f.code for f in issues] == ["HOOK_DEAD_AIR", "DURATION_SHORT", "HOOK_NO_CUT"]
    assert [f.code for f in report.priorities(2)] == ["HOOK_DEAD_AIR", "DURATION_SHORT"]
    assert [f.code for f in report.strengths()] == ["AUDIO_GOOD"]
    assert report.category("audio") is report.categories[1]
    assert report.category("nope") is None


def test_all_emitted_codes_have_message_templates():
    assert sample_report().codes <= set(CATALOG)


# -------------------------------------------------------------- renderers
def test_json_round_trip_keeps_scores_and_findings():
    payload = json.loads(to_json(sample_report()))

    assert payload["platform"] == "tiktok"
    assert payload["grade"] == grade_for(payload["score"])
    hook = next(c for c in payload["categories"] if c["key"] == "hook")
    assert hook["findings"][0]["code"] == "HOOK_DEAD_AIR"
    assert payload["thumbnail_candidates"][0]["time"] == 3.5


@pytest.mark.parametrize("lang", ["he", "en"])
def test_markdown_contains_every_section(lang: str):
    output = to_markdown(sample_report(), lang=lang)

    for key in ("report_title", "priorities", "what_to_fix", "what_works", "categories", "metrics"):
        assert messages.ui(key, lang) in output
    assert "HOOK_DEAD_AIR" not in output  # codes stay internal
    assert messages.category_label("hook", lang) in output


def test_text_output_is_compact_and_shows_the_score():
    output = to_text(sample_report(), lang="he")

    assert "/100" in output
    assert output.count("\n") < 40
    assert messages.ui("priorities", "he") in output


@pytest.mark.parametrize("lang,direction", [("he", "rtl"), ("en", "ltr")])
def test_html_is_self_contained_and_direction_aware(lang: str, direction: str):
    output = to_html(sample_report(), lang=lang)

    assert output.startswith("<!DOCTYPE html>")
    assert f'dir="{direction}"' in output
    assert "<style>" in output
    assert "src=" not in output  # no external assets when no files were exported
    assert "conic-gradient" in output


def test_html_escapes_report_content():
    report = sample_report()
    report.file = '<script>alert("x")</script>'
    output = to_html(report, lang="en")

    assert "<script>alert" not in output
    assert "&lt;script&gt;" in output


def test_renderers_handle_a_report_without_issues():
    categories = [
        CategoryResult(
            key="audio",
            score=100.0,
            findings=[Finding(code="AUDIO_GOOD", severity=Severity.GOOD, params={"lufs": -14.0})],
        )
    ]
    apply_weights(categories, {"audio": 1.0})
    report = Report(
        file="clean.mp4",
        platform="generic",
        score=100.0,
        media={"duration": 30.0, "width": 1920, "height": 1080, "fps": 30, "has_audio": True},
        categories=categories,
    )

    assert messages.ui("no_issues", "he") in to_markdown(report)
    assert messages.ui("no_issues", "en") in to_html(report, lang="en")
    assert "100" in to_text(report)
