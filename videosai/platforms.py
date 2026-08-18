"""Platform presets: what "good" means changes per destination."""

from __future__ import annotations

from dataclasses import dataclass, field


@dataclass(frozen=True)
class Platform:
    key: str
    label_he: str
    label_en: str
    #: Target width / height. 9/16 for vertical short form, 16/9 for YouTube.
    target_aspect: float
    aspect_tolerance: float
    min_height: int
    min_fps: float
    #: Duration window that tends to perform well, in seconds.
    ideal_duration: tuple[float, float]
    #: Hard platform limit; going over means the upload gets cut or rejected.
    max_duration: float | None
    #: Integrated loudness target in LUFS.
    loudness_target: float
    loudness_tolerance: float
    cuts_per_minute: tuple[float, float]
    #: Seconds of near-frozen frame the audience will forgive.
    max_static_seconds: float
    #: Fraction of the frame covered by platform UI (captions, buttons).
    safe_zone_top: float = 0.0
    safe_zone_bottom: float = 0.0
    weights: dict[str, float] = field(default_factory=dict)

    @property
    def is_vertical(self) -> bool:
        return self.target_aspect < 1.0

    def label(self, lang: str = "he") -> str:
        return self.label_he if lang == "he" else self.label_en


_SHORT_FORM_WEIGHTS = {
    "hook": 0.28,
    "pacing": 0.16,
    "audio": 0.20,
    "visual": 0.14,
    "framing": 0.13,
    "duration": 0.09,
}

_LONG_FORM_WEIGHTS = {
    "hook": 0.22,
    "pacing": 0.14,
    "audio": 0.24,
    "visual": 0.18,
    "framing": 0.14,
    "duration": 0.08,
}

TIKTOK = Platform(
    key="tiktok",
    label_he="טיקטוק",
    label_en="TikTok",
    target_aspect=9 / 16,
    aspect_tolerance=0.06,
    min_height=1080,
    min_fps=24,
    ideal_duration=(15, 60),
    max_duration=600,
    loudness_target=-14.0,
    loudness_tolerance=3.0,
    cuts_per_minute=(12, 90),
    max_static_seconds=3.0,
    safe_zone_top=0.10,
    safe_zone_bottom=0.20,
    weights=_SHORT_FORM_WEIGHTS,
)

REELS = Platform(
    key="reels",
    label_he="אינסטגרם ריאלס",
    label_en="Instagram Reels",
    target_aspect=9 / 16,
    aspect_tolerance=0.06,
    min_height=1080,
    min_fps=24,
    ideal_duration=(15, 60),
    max_duration=180,
    loudness_target=-14.0,
    loudness_tolerance=3.0,
    cuts_per_minute=(12, 90),
    max_static_seconds=3.0,
    safe_zone_top=0.10,
    safe_zone_bottom=0.22,
    weights=_SHORT_FORM_WEIGHTS,
)

SHORTS = Platform(
    key="shorts",
    label_he="יוטיוב שורטס",
    label_en="YouTube Shorts",
    target_aspect=9 / 16,
    aspect_tolerance=0.06,
    min_height=1080,
    min_fps=24,
    ideal_duration=(20, 60),
    max_duration=180,
    loudness_target=-14.0,
    loudness_tolerance=3.0,
    cuts_per_minute=(10, 80),
    max_static_seconds=3.5,
    safe_zone_top=0.08,
    safe_zone_bottom=0.18,
    weights=_SHORT_FORM_WEIGHTS,
)

YOUTUBE = Platform(
    key="youtube",
    label_he="יוטיוב (סרטון ארוך)",
    label_en="YouTube (long form)",
    target_aspect=16 / 9,
    aspect_tolerance=0.08,
    min_height=1080,
    min_fps=24,
    ideal_duration=(180, 1500),
    max_duration=None,
    loudness_target=-14.0,
    loudness_tolerance=3.0,
    cuts_per_minute=(6, 45),
    max_static_seconds=6.0,
    weights=_LONG_FORM_WEIGHTS,
)

INSTAGRAM_FEED = Platform(
    key="instagram_feed",
    label_he="פוסט וידאו באינסטגרם",
    label_en="Instagram feed video",
    target_aspect=4 / 5,
    aspect_tolerance=0.12,
    min_height=1080,
    min_fps=24,
    ideal_duration=(10, 60),
    max_duration=600,
    loudness_target=-14.0,
    loudness_tolerance=3.0,
    cuts_per_minute=(8, 70),
    max_static_seconds=4.0,
    safe_zone_top=0.06,
    safe_zone_bottom=0.10,
    weights=_SHORT_FORM_WEIGHTS,
)

GENERIC = Platform(
    key="generic",
    label_he="כללי",
    label_en="Generic",
    target_aspect=0.0,  # 0 disables the aspect ratio check
    aspect_tolerance=0.0,
    min_height=720,
    min_fps=24,
    ideal_duration=(5, 3600),
    max_duration=None,
    loudness_target=-14.0,
    loudness_tolerance=4.0,
    cuts_per_minute=(0, 200),
    max_static_seconds=10.0,
    weights=_LONG_FORM_WEIGHTS,
)

PLATFORMS: dict[str, Platform] = {
    p.key: p for p in (TIKTOK, REELS, SHORTS, YOUTUBE, INSTAGRAM_FEED, GENERIC)
}

_ALIASES = {
    "tt": "tiktok",
    "instagram": "reels",
    "ig": "reels",
    "reel": "reels",
    "yt": "youtube",
    "youtube_shorts": "shorts",
    "short": "shorts",
    "feed": "instagram_feed",
}


def get_platform(key: str) -> Platform:
    normalized = key.strip().lower().replace("-", "_")
    normalized = _ALIASES.get(normalized, normalized)
    try:
        return PLATFORMS[normalized]
    except KeyError:
        options = ", ".join(sorted(PLATFORMS))
        raise KeyError(f"Unknown platform '{key}'. Available: {options}") from None
