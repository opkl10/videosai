"""The first seconds decide whether anyone watches the rest."""

from __future__ import annotations

from ..models import CategoryResult, Finding, Severity
from .base import AnalysisContext, scaled_penalty, score_from

KEY = "hook"
HOOK_WINDOW = 3.0
DEAD_AIR_SECONDS = 0.7
STATIC_MOTION = 0.8
STATIC_RATIO = 0.45
DARK_LUMA = 45.0
SOFT_SHARPNESS = 70.0


def analyze(context: AnalysisContext) -> CategoryResult:
    timeline = context.timeline
    window = min(HOOK_WINDOW, context.duration)
    interval = timeline.interval or 0.25

    hook_motion = timeline.mean(timeline.motion, interval, window)
    overall_motion = timeline.mean(timeline.motion, interval)
    hook_luma = timeline.mean(timeline.luma, 0.0, window)
    hook_cuts = context.cuts_between(0.0, window)
    hook_keyframes = context.keyframes_between(0.0, max(window, 1.0)) or context.keyframes[:1]
    hook_sharpness = min((k.sharpness for k in hook_keyframes), default=0.0)
    lead_in = context.audio.lead_in_silence if context.audio else 0.0

    findings: list[Finding] = []

    if context.audio is not None and lead_in > DEAD_AIR_SECONDS:
        findings.append(
            Finding(
                code="HOOK_DEAD_AIR",
                severity=Severity.CRITICAL if lead_in > 1.2 else Severity.WARNING,
                penalty=scaled_penalty(lead_in - DEAD_AIR_SECONDS, 2.0, 14, 30),
                params={"lead_in": round(lead_in, 2)},
                at=0.0,
            )
        )

    too_static = hook_motion < STATIC_MOTION or (
        overall_motion > 0 and hook_motion < STATIC_RATIO * overall_motion
    )
    if too_static and len(timeline) > 2:
        findings.append(
            Finding(
                code="HOOK_STATIC",
                severity=Severity.WARNING,
                penalty=20.0,
                params={
                    "hook_motion": round(hook_motion, 2),
                    "motion_overall": round(overall_motion, 2),
                },
                at=0.0,
            )
        )

    if not hook_cuts and context.platform.is_vertical and context.duration >= 10:
        findings.append(
            Finding(code="HOOK_NO_CUT", severity=Severity.INFO, penalty=8.0, at=0.0)
        )

    if hook_luma < DARK_LUMA:
        findings.append(
            Finding(
                code="HOOK_DARK_OPEN",
                severity=Severity.WARNING,
                penalty=scaled_penalty(DARK_LUMA - hook_luma, DARK_LUMA, 10, 22),
                params={"hook_brightness": round(hook_luma, 1)},
                at=0.0,
            )
        )

    if hook_sharpness and hook_sharpness < SOFT_SHARPNESS:
        findings.append(
            Finding(
                code="HOOK_SOFT_FOCUS_OPEN",
                severity=Severity.WARNING,
                penalty=12.0,
                params={
                    "hook_sharpness": round(hook_sharpness, 1),
                    "threshold": SOFT_SHARPNESS,
                },
                at=hook_keyframes[0].time if hook_keyframes else 0.0,
            )
        )

    if not findings:
        findings.append(Finding(code="HOOK_STRONG", severity=Severity.GOOD))

    return CategoryResult(
        key=KEY,
        score=score_from(findings),
        metrics={
            "cuts_in_hook": len(hook_cuts),
            "hook_motion": round(hook_motion, 2),
            "motion_overall": round(overall_motion, 2),
            "hook_brightness": round(hook_luma, 1),
            "hook_sharpness": round(hook_sharpness, 1),
            "lead_in_silence": round(lead_in, 2),
        },
        findings=findings,
    )
