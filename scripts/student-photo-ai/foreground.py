"""Remove old background colors from partially transparent portrait edges."""
import numpy as np
from PIL import Image
from pymatting import estimate_foreground_ml


def clean_foreground(image: Image.Image, alpha: Image.Image, *, color_alpha: Image.Image | None = None) -> Image.Image:
    rgb = np.asarray(image.convert('RGB'), dtype=np.float32) / 255.0
    opacity = np.asarray(alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32)
    opacity = np.clip(opacity, 0, 1)
    # Drop only near-invisible model noise. Do not erode the silhouette: hair
    # needs its intermediate alpha values to blend naturally on white.
    opacity[opacity < 0.005] = 0
    opacity[opacity > 0.995] = 1
    # Fabric contour smoothing intentionally narrows the soft edge. Its final
    # alpha describes the new silhouette, not how much old background was in
    # the source RGB. Keep the pre-smoothing matte for color reconstruction,
    # including pixels that became fully opaque along the shoulder.
    color_opacity = opacity if color_alpha is None else np.clip(
        np.asarray(color_alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    if np.any((color_opacity > 0) & (color_opacity < 1)):
        # Source RGB already contains the old background mixed into soft edges.
        # Estimate foreground RGB before applying alpha to a new background,
        # rather than applying alpha to those contaminated RGB values twice.
        foreground = estimate_foreground_ml(rgb, color_opacity, n_big_iterations=4)
        foreground[color_opacity == 1] = rgb[color_opacity == 1]
    else:
        foreground = rgb
    rgba = np.dstack((np.clip(foreground, 0, 1), opacity))
    return Image.fromarray(np.rint(rgba * 255).astype(np.uint8))
