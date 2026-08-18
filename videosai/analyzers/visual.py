"""Focus, exposure, contrast and colour."""

from __future__ import annotations

import numpy as np

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, scaled_penalty, score_from

KEY = "visual"
SOFT_SHARPNESS = 80.0
DARK_LUMA = 60.0
BRIGHT_LUMA = 200.0
CLIPPED_HIGHLIGHTS_PERCENT = 4.0
FLAT_CONTRAST = 28.0
LOW_SATURATION = 0.12
DARK_SEGMENT_LUMA = 35.0
DARK_SEGMENT_SECONDS = 1.5


def analyze(context: AnalysisContext) -> CategoryResult:
    timeline = context.timeline
    brightness = timeline.median(timeline.luma)
    contrast = timeline.median(timeline.contrast)
    saturation = timeline.median(timeline.saturation)
    clipped = timeline.median(timeline.clipped_high)
    sharpness = (
        float(np.median([k.sharpness for k in context.keyframes])) if context.keyframes else 0.0
    )

    findings: list[Finding] = []

    if context.keyframes and sharpness < SOFT_SHARPNESS:
        findings.append(
            Finding(
                code="VISUAL_SOFT_FOCUS",
                severity=Severity.WARNING,
                penalty=scaled_penalty(SOFT_SHARPNESS - sharpness, SOFT_SHARPNESS, 10, 25),
                params={"sharpness": round(sharpness, 1), "threshold": SOFT_SHARPNESS},
            )
        )

    if brightness < DARK_LUMA:
        findings.append(
            Finding(
                code="VISUAL_UNDEREXPOSED",
                severity=Severity.WARNING if brightness > 30 else Severity.CRITICAL,
                penalty=scaled_penalty(DARK_LUMA - brightness, DARK_LUMA, 12, 30),
                params={"brightness": round(brightness, 1)},
            )
        )
    elif brightness > BRIGHT_LUMA or clipped > CLIPPED_HIGHLIGHTS_PERCENT:
        findings.append(
            Finding(
                code="VISUAL_OVEREXPOSED",
                severity=Severity.WARNING,
                penalty=scaled_penalty(clipped - CLIPPED_HIGHLIGHTS_PERCENT, 15.0, 10, 22),
                params={"clipped": round(clipped, 1)},
            )
        )

    if contrast < FLAT_CONTRAST:
        findings.append(
            Finding(
                code="VISUAL_FLAT",
                severity=Severity.INFO,
                penalty=8.0,
                params={"contrast": round(contrast, 1)},
            )
        )

    if saturation < LOW_SATURATION:
        findings.append(
            Finding(
                code="VISUAL_DESATURATED",
                severity=Severity.INFO,
                penalty=6.0,
                params={"saturation": round(saturation, 3)},
            )
        )

    dark_segments = timeline.dark_segments(DARK_SEGMENT_LUMA, DARK_SEGMENT_SECONDS)
    # A wholly dark video is already covered by the underexposure finding.
    dark_seconds = sum(length for _, length in dark_segments)
    if dark_segments and dark_seconds < context.duration * 0.9:
        findings.append(
            Finding(
                code="VISUAL_DARK_SEGMENTS",
                severity=Severity.WARNING,
                penalty=min(18.0, 5.0 * len(dark_segments)),
                params={"segments": len(dark_segments), "dark_seconds": round(dark_seconds, 1)},
                timestamps=tuple(start for start, _ in dark_segments[:6]),
            )
        )

    if not findings:
        findings.append(Finding(code="VISUAL_GOOD", severity=Severity.GOOD))

    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "sharpness": round(sharpness, 1),
            "brightness": round(brightness, 1),
            "contrast": round(contrast, 1),
            "saturation": round(saturation, 3),
            "clipped_highlights": round(clipped, 2),
            "dark_seconds": round(dark_seconds, 1),
        },
        findings=findings,
    )
