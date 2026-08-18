"""Aspect ratio, resolution and the platform's UI safe zones."""

from __future__ import annotations

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, score_from, scaled_penalty

KEY = "framing"
#: How much busier than the centre a safe zone may be before it looks like
#: text or graphics were placed under the platform UI.
SAFE_ZONE_RATIO = 1.15
LOW_BITS_PER_PIXEL = 0.04


def analyze(context: AnalysisContext) -> CategoryResult:
    info = context.info
    platform = context.platform
    timeline = context.timeline
    findings: list[Finding] = []

    aspect = info.aspect_ratio
    if platform.target_aspect > 0:
        difference = abs(aspect - platform.target_aspect)
        if difference > platform.aspect_tolerance:
            findings.append(
                Finding(
                    code="FRAMING_ASPECT",
                    severity=Severity.CRITICAL if difference > 0.3 else Severity.WARNING,
                    penalty=scaled_penalty(difference - platform.aspect_tolerance, 0.8, 12, 35),
                    params={
                        "platform": platform.key,
                        "actual": round(aspect, 3),
                        "target": round(platform.target_aspect, 3),
                        "width": info.width,
                        "height": info.height,
                    },
                )
            )

    short_side = min(info.width, info.height)
    if short_side < platform.min_height:
        findings.append(
            Finding(
                code="FRAMING_LOW_RES",
                severity=Severity.WARNING,
                penalty=scaled_penalty(platform.min_height - short_side, platform.min_height, 8, 20),
                params={"width": info.width, "height": info.height, "min_height": platform.min_height},
            )
        )

    if info.fps and info.fps < platform.min_fps:
        findings.append(
            Finding(
                code="FRAMING_LOW_FPS",
                severity=Severity.WARNING,
                penalty=10.0,
                params={"fps": info.fps, "min_fps": platform.min_fps},
            )
        )

    middle = timeline.mean(timeline.edge_middle)
    if middle > 0.5:
        if platform.safe_zone_top > 0:
            top_ratio = timeline.mean(timeline.edge_top) / middle
            if top_ratio > SAFE_ZONE_RATIO:
                findings.append(
                    Finding(
                        code="FRAMING_SAFE_ZONE_TOP",
                        severity=Severity.INFO,
                        penalty=8.0,
                        params={"percent": platform.safe_zone_top * 100},
                    )
                )
        if platform.safe_zone_bottom > 0:
            bottom_ratio = timeline.mean(timeline.edge_bottom) / middle
            if bottom_ratio > SAFE_ZONE_RATIO:
                findings.append(
                    Finding(
                        code="FRAMING_SAFE_ZONE_BOTTOM",
                        severity=Severity.INFO,
                        penalty=8.0,
                        params={"percent": platform.safe_zone_bottom * 100},
                    )
                )

    bits_per_pixel = info.bits_per_pixel
    if bits_per_pixel is not None and bits_per_pixel < LOW_BITS_PER_PIXEL:
        findings.append(
            Finding(
                code="FRAMING_LOW_BITRATE",
                severity=Severity.INFO,
                penalty=6.0,
                params={"bpp": bits_per_pixel},
            )
        )

    if not findings:
        findings.append(
            Finding(
                code="FRAMING_GOOD",
                severity=Severity.GOOD,
                params={
                    "width": info.width,
                    "height": info.height,
                    "fps": info.fps,
                    "platform": platform.key,
                },
            )
        )

    middle_energy = middle or 1.0
    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "aspect_ratio": round(aspect, 3),
            "resolution": f"{info.width}x{info.height}",
            "fps": round(info.fps, 2),
            "bits_per_pixel": round(bits_per_pixel, 4) if bits_per_pixel is not None else None,
            "safe_zone_top_ratio": round(timeline.mean(timeline.edge_top) / middle_energy, 2),
            "safe_zone_bottom_ratio": round(timeline.mean(timeline.edge_bottom) / middle_energy, 2),
        },
        findings=findings,
    )
