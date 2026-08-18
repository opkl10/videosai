"""Analyzer registry. Order here is the order used in reports."""

from __future__ import annotations

from collections.abc import Callable

from ..models import CategoryResult
from . import audio, duration, framing, hook, pacing, visual
from .base import AnalysisContext

AnalyzerFn = Callable[[AnalysisContext], CategoryResult]

ANALYZERS: dict[str, AnalyzerFn] = {
    hook.KEY: hook.analyze,
    pacing.KEY: pacing.analyze,
    audio.KEY: audio.analyze,
    visual.KEY: visual.analyze,
    framing.KEY: framing.analyze,
    duration.KEY: duration.analyze,
}

__all__ = ["ANALYZERS", "AnalysisContext", "AnalyzerFn"]
