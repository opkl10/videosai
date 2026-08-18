"""Frame feature extraction and timeline queries."""

from __future__ import annotations

import numpy as np
import pytest

from videosai.features import (
    Keyframe,
    edge_energy,
    keyframe_times,
    merge_cuts,
    motion_cuts,
    saturation_of,
    sharpness_of,
    to_luma,
)

from .conftest import flat_frame, noise_frame, timeline_from


def test_luma_of_grey_frame_matches_pixel_value():
    frame = flat_frame(brightness=100)
    assert to_luma(frame).mean() == pytest.approx(100.0, abs=0.5)


def test_textured_frame_is_sharper_than_flat_frame():
    textured = sharpness_of(to_luma(noise_frame()))
    flat = sharpness_of(to_luma(flat_frame()))
    assert textured > 1000
    assert flat == pytest.approx(0.0)


def test_saturation_separates_grey_from_colour():
    grey = flat_frame(brightness=120)
    red = np.zeros((16, 16, 3), dtype=np.uint8)
    red[:, :, 0] = 200
    assert saturation_of(grey) == pytest.approx(0.0, abs=1e-6)
    assert saturation_of(red) == pytest.approx(1.0, abs=1e-6)


def test_edge_energy_is_higher_for_busy_regions():
    assert edge_energy(to_luma(noise_frame())) > edge_energy(to_luma(flat_frame()))


def test_timeline_tracks_brightness_and_motion():
    frames = [flat_frame(brightness=40)] * 8 + [flat_frame(brightness=200)] * 8
    timeline = timeline_from(frames, fps=4.0)

    assert len(timeline) == 16
    assert timeline.mean(timeline.luma, 0.0, 2.0) == pytest.approx(40, abs=1)
    assert timeline.mean(timeline.luma, 2.0) == pytest.approx(200, abs=1)
    # Only the single frame where brightness jumps should register as motion.
    assert float(timeline.motion.max()) > 100
    assert timeline.motion[1] == pytest.approx(0.0)


def test_longest_static_run_finds_the_frozen_stretch():
    frames = [noise_frame(seed=i) for i in range(8)] + [flat_frame()] * 20
    timeline = timeline_from(frames, fps=4.0)

    static = timeline.longest_static_run(threshold=0.7, min_seconds=3.0)
    assert static is not None
    start, length = static
    assert start == pytest.approx(2.25, abs=0.3)
    assert length == pytest.approx(4.75, abs=0.3)


def test_no_static_run_when_every_frame_changes():
    timeline = timeline_from([noise_frame(seed=i) for i in range(20)], fps=4.0)
    assert timeline.longest_static_run(threshold=0.7, min_seconds=1.0) is None


def test_dark_segments_reports_start_and_length():
    frames = [noise_frame(seed=i) for i in range(4)] + [flat_frame(brightness=10)] * 12
    timeline = timeline_from(frames, fps=4.0)

    segments = timeline.dark_segments(threshold=35.0, min_seconds=1.5)
    assert len(segments) == 1
    start, length = segments[0]
    assert start == pytest.approx(1.0, abs=0.3)
    assert length == pytest.approx(3.0, abs=0.3)


def test_motion_cuts_flags_spikes_only():
    frames = []
    for index in range(24):
        brightness = 200 if 8 <= index < 16 else 60
        frames.append(noise_frame(brightness=brightness, seed=index))
    timeline = timeline_from(frames, fps=4.0)

    cuts = motion_cuts(timeline)
    assert cuts == pytest.approx([2.0, 4.0])


def test_merge_cuts_drops_near_duplicates():
    assert merge_cuts([2.0, 6.0], [2.2, 9.0], tolerance=0.5) == [2.0, 6.0, 9.0]
    assert merge_cuts([], [1.0]) == [1.0]


def test_keyframe_times_are_spread_and_inside_the_clip():
    times = keyframe_times(10.0, count=5)
    assert len(times) == 5
    assert times[0] > 0
    assert times[-1] < 10.0
    assert times == sorted(times)


def test_keyframe_times_handles_single_sample():
    assert keyframe_times(0.0, count=1) == [0.0]
    assert len(keyframe_times(10.0, count=1)) == 1


def test_cover_score_prefers_sharp_well_exposed_frames():
    good = Keyframe(time=1, sharpness=400, brightness=130, contrast=55, saturation=0.35, clipped_high=0)
    dark = Keyframe(time=2, sharpness=400, brightness=15, contrast=55, saturation=0.35, clipped_high=0)
    blurry = Keyframe(time=3, sharpness=20, brightness=130, contrast=55, saturation=0.35, clipped_high=0)
    burned = Keyframe(time=4, sharpness=400, brightness=130, contrast=55, saturation=0.35, clipped_high=30)

    assert good.cover_score > dark.cover_score
    assert good.cover_score > blurry.cover_score
    assert good.cover_score > burned.cover_score
    assert 0 <= burned.cover_score <= 100
