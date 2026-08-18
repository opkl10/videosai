"""Each analyzer, driven by synthetic contexts."""

from __future__ import annotations

from videosai.analyzers import audio, duration, framing, hook, pacing, visual

from .conftest import (
    flat_frame,
    make_audio,
    make_context,
    make_info,
    make_keyframes,
    noise_frame,
    timeline_from,
)


def codes(result) -> set[str]:
    return {finding.code for finding in result.findings}


# ------------------------------------------------------------------- hook
def test_healthy_hook_scores_full_marks():
    result = hook.analyze(make_context())
    assert result.score == 100
    assert codes(result) == {"HOOK_STRONG"}


def test_dead_air_at_the_start_is_critical():
    context = make_context(audio=make_audio(silences=[(0.0, 1.9)]))
    result = hook.analyze(context)

    assert "HOOK_DEAD_AIR" in codes(result)
    finding = next(f for f in result.findings if f.code == "HOOK_DEAD_AIR")
    assert finding.severity.value == "critical"
    assert result.score < 80


def test_frozen_opening_is_flagged_as_static():
    frames = [flat_frame()] * 12 + [noise_frame(seed=i) for i in range(60)]
    context = make_context(timeline=timeline_from(frames))
    result = hook.analyze(context)

    assert "HOOK_STATIC" in codes(result)
    assert result.metrics["hook_motion"] < result.metrics["motion_overall"]


def test_dark_and_soft_opening_are_reported_together():
    frames = [flat_frame(brightness=12)] * 12 + [noise_frame(seed=i) for i in range(60)]
    context = make_context(
        timeline=timeline_from(frames),
        keyframes=make_keyframes(sharpness=20.0),
    )
    result = hook.analyze(context)

    assert {"HOOK_DARK_OPEN", "HOOK_SOFT_FOCUS_OPEN"} <= codes(result)


def test_missing_cut_in_hook_only_matters_for_vertical_platforms():
    vertical = hook.analyze(make_context(cuts=[8.0, 12.0]))
    horizontal = hook.analyze(make_context(platform="youtube", cuts=[8.0, 12.0]))

    assert "HOOK_NO_CUT" in codes(vertical)
    assert "HOOK_NO_CUT" not in codes(horizontal)


# ----------------------------------------------------------------- pacing
def test_pacing_is_good_with_regular_cuts():
    result = pacing.analyze(make_context())
    assert codes(result) == {"PACING_GOOD"}
    assert result.metrics["cuts_per_minute"] == 18.0


def test_slow_pacing_penalty_grows_with_the_gap():
    barely = pacing.analyze(make_context(cuts=[3.0, 9.0, 15.0]))
    none = pacing.analyze(make_context(cuts=[]))

    assert "PACING_SLOW" in codes(barely)
    assert "PACING_SLOW" in codes(none)
    assert none.score < barely.score


def test_frozen_shot_is_reported_with_its_timestamp():
    frames = [noise_frame(seed=i) for i in range(20)] + [flat_frame()] * 40
    result = pacing.analyze(make_context(timeline=timeline_from(frames)))

    finding = next(f for f in result.findings if f.code == "PACING_FROZEN_SHOT")
    assert finding.at is not None and finding.at > 4.0
    assert result.metrics["longest_static_seconds"] > 3.0


def test_frantic_editing_is_flagged():
    result = pacing.analyze(make_context(cuts=[i * 0.25 for i in range(1, 80)]))
    assert "PACING_FRANTIC" in codes(result)


def test_short_clips_are_not_judged_on_cut_rate():
    context = make_context(info=make_info(duration=5.0), cuts=[])
    result = pacing.analyze(context)

    assert "PACING_SLOW" not in codes(result)
    assert "PACING_STABLE" in codes(result)


# ------------------------------------------------------------------ audio
def test_missing_audio_track_is_the_heaviest_audio_penalty():
    result = audio.analyze(make_context(info=make_info(has_audio=False), audio=None))
    assert codes(result) == {"AUDIO_MISSING"}
    assert result.score < 50


def test_quiet_audio_reports_the_gap_to_target():
    result = audio.analyze(make_context(audio=make_audio(integrated_lufs=-28.0)))
    finding = next(f for f in result.findings if f.code == "AUDIO_QUIET")

    assert finding.params["delta"] == 14.0
    assert result.score < 80


def test_loud_and_clipping_audio_are_separate_findings():
    result = audio.analyze(make_context(audio=make_audio(integrated_lufs=-6.0, true_peak_db=0.4)))
    assert {"AUDIO_LOUD", "AUDIO_CLIPPING"} <= codes(result)


def test_rms_fallback_uses_a_wider_tolerance():
    strict = audio.analyze(make_context(audio=make_audio(integrated_lufs=-18.5)))
    lenient = audio.analyze(
        make_context(audio=make_audio(integrated_lufs=-18.5, loudness_source="volumedetect"))
    )

    assert "AUDIO_QUIET" in codes(strict)
    assert "AUDIO_QUIET" not in codes(lenient)


def test_long_silent_gaps_are_listed_but_lead_out_is_ignored():
    stats = make_audio(silences=[(4.0, 6.0), (9.0, 11.0), (19.5, 20.0)], duration=20.0)
    result = audio.analyze(make_context(audio=stats))

    finding = next(f for f in result.findings if f.code == "AUDIO_DEAD_GAPS")
    assert finding.params["gaps"] == 2
    assert finding.timestamps == (4.0, 9.0)


def test_flat_dynamics_and_low_sample_rate_are_informational():
    context = make_context(
        info=make_info(audio_sample_rate=16000),
        audio=make_audio(loudness_range=0.2),
    )
    result = audio.analyze(context)

    assert {"AUDIO_OVERCOMPRESSED", "AUDIO_LOW_SAMPLE_RATE"} <= codes(result)
    assert all(f.severity.value == "info" for f in result.findings)


# ----------------------------------------------------------------- visual
def test_clean_image_scores_full_marks():
    result = visual.analyze(make_context())
    assert codes(result) == {"VISUAL_GOOD"}


def test_underexposed_video_is_critical_when_very_dark():
    frames = [flat_frame(brightness=12)] * 40
    result = visual.analyze(make_context(timeline=timeline_from(frames)))

    finding = next(f for f in result.findings if f.code == "VISUAL_UNDEREXPOSED")
    assert finding.severity.value == "critical"


def test_blown_highlights_are_reported():
    frames = [flat_frame(brightness=254)] * 40
    result = visual.analyze(make_context(timeline=timeline_from(frames)))
    assert "VISUAL_OVEREXPOSED" in codes(result)


def test_flat_and_desaturated_image_is_noted():
    frames = [flat_frame(brightness=130)] * 40
    result = visual.analyze(make_context(timeline=timeline_from(frames)))
    assert {"VISUAL_FLAT", "VISUAL_DESATURATED"} <= codes(result)


def test_soft_focus_uses_the_keyframe_sharpness():
    result = visual.analyze(make_context(keyframes=make_keyframes(sharpness=30.0)))
    assert "VISUAL_SOFT_FOCUS" in codes(result)


def test_partly_dark_video_lists_the_dark_segments():
    frames = [noise_frame(seed=i) for i in range(40)] + [flat_frame(brightness=10)] * 24
    result = visual.analyze(make_context(timeline=timeline_from(frames)))

    finding = next(f for f in result.findings if f.code == "VISUAL_DARK_SEGMENTS")
    assert finding.params["segments"] == 1
    assert finding.timestamps[0] > 9.0


def test_fully_dark_video_is_not_double_penalized():
    frames = [flat_frame(brightness=10)] * 40
    result = visual.analyze(make_context(timeline=timeline_from(frames)))

    assert "VISUAL_UNDEREXPOSED" in codes(result)
    assert "VISUAL_DARK_SEGMENTS" not in codes(result)


# ---------------------------------------------------------------- framing
def test_correct_vertical_format_scores_full_marks():
    result = framing.analyze(make_context())
    assert codes(result) == {"FRAMING_GOOD"}


def test_horizontal_video_on_tiktok_is_critical():
    context = make_context(info=make_info(width=1920, height=1080))
    result = framing.analyze(context)

    finding = next(f for f in result.findings if f.code == "FRAMING_ASPECT")
    assert finding.severity.value == "critical"
    assert result.score < 70


def test_same_video_is_fine_on_youtube():
    context = make_context(platform="youtube", info=make_info(width=1920, height=1080))
    assert "FRAMING_ASPECT" not in codes(framing.analyze(context))


def test_low_resolution_frame_rate_and_bitrate_are_reported():
    context = make_context(
        info=make_info(width=540, height=960, fps=20.0, video_bitrate=200_000, total_bitrate=210_000)
    )
    result = framing.analyze(context)

    assert {"FRAMING_LOW_RES", "FRAMING_LOW_FPS", "FRAMING_LOW_BITRATE"} <= codes(result)


def test_busy_safe_zones_are_flagged_for_vertical_platforms():
    busy_edges = noise_frame(brightness=130, spread=60, seed=1)
    busy_edges[30:130] = 130  # calm middle, textured top and bottom
    context = make_context(timeline=timeline_from([busy_edges] * 40))
    result = framing.analyze(context)

    assert {"FRAMING_SAFE_ZONE_TOP", "FRAMING_SAFE_ZONE_BOTTOM"} <= codes(result)


def test_safe_zones_are_ignored_when_the_platform_has_no_ui_overlay():
    busy_edges = noise_frame(brightness=130, spread=60, seed=1)
    busy_edges[30:130] = 130
    context = make_context(
        platform="youtube",
        info=make_info(width=1920, height=1080),
        timeline=timeline_from([busy_edges] * 40),
    )
    result = framing.analyze(context)

    assert not {"FRAMING_SAFE_ZONE_TOP", "FRAMING_SAFE_ZONE_BOTTOM"} & codes(result)


# --------------------------------------------------------------- duration
def test_duration_inside_the_window_is_good():
    result = duration.analyze(make_context(info=make_info(duration=30.0)))
    assert codes(result) == {"DURATION_GOOD"}


def test_too_short_and_too_long_are_scaled():
    tiny = duration.analyze(make_context(info=make_info(duration=3.0)))
    slightly_long = duration.analyze(make_context(info=make_info(duration=70.0)))

    assert "DURATION_SHORT" in codes(tiny)
    assert "DURATION_LONG" in codes(slightly_long)
    assert tiny.score < slightly_long.score


def test_over_the_platform_limit_is_critical():
    result = duration.analyze(make_context(platform="reels", info=make_info(duration=240.0)))
    finding = next(f for f in result.findings if f.code == "DURATION_OVER_LIMIT")

    assert finding.severity.value == "critical"
    assert result.score < 60


def test_long_video_is_judged_per_platform():
    context = make_context(platform="youtube", info=make_info(duration=600.0, width=1920, height=1080))
    assert codes(duration.analyze(context)) == {"DURATION_GOOD"}
