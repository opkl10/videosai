"""Exceptions raised by videosai."""

from __future__ import annotations


class VideosaiError(Exception):
    """Base class for all videosai errors."""


class FFmpegMissingError(VideosaiError):
    def __init__(self, tool: str) -> None:
        super().__init__(
            f"'{tool}' was not found on PATH. Install FFmpeg (https://ffmpeg.org/download.html) "
            "and make sure both 'ffmpeg' and 'ffprobe' are available."
        )
        self.tool = tool


class MediaError(VideosaiError):
    """The input file could not be read or contains no usable video stream."""
