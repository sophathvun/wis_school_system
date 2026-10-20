"""Refine the model's coarse boundary using the original portrait pixels."""
import warnings
import numpy as np
from PIL import Image
from scipy.ndimage import maximum_filter, minimum_filter
from pymatting import estimate_alpha_cf


def refine_alpha(image: Image.Image, alpha: Image.Image) -> Image.Image:
    opacity = np.clip(np.asarray(alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    height, width = opacity.shape
    radius = max(2, min(4, round(max(image.size) / 400)))
    # Reconsider a narrow band on BOTH sides of the predicted boundary. This
    # lets matting remove an opaque pale halo as well as restore fine hairs.
    foreground = minimum_filter(opacity, size=2 * radius + 1, mode='nearest') > .98
    background = maximum_filter(opacity, size=2 * radius + 1, mode='nearest') < .02
    unknown = ~(foreground | background)
    if not foreground.any() or not background.any() or not unknown.any():
        return Image.fromarray(opacity)

    rgb = np.asarray(image.convert('RGB'), dtype=np.float64) / 255.0
    trimap = np.full((height, width), .5, dtype=np.float64)
    trimap[foreground] = 1
    trimap[background] = 0
    total = np.zeros_like(opacity)
    weights = np.zeros_like(opacity)
    # Closed-form matting allocates a sparse Laplacian. Overlapping 256px
    # patches keep memory bounded even for the maximum 1600px portrait.
    tile, overlap = 256, 64
    for top in range(0, height, tile - overlap):
        for left in range(0, width, tile - overlap):
            bottom, right = min(top + tile, height), min(left + tile, width)
            region = np.s_[top:bottom, left:right]
            patch_trimap = trimap[region]
            if not unknown[region].any() or not np.any(patch_trimap == 1) or not np.any(patch_trimap == 0):
                continue
            # Very narrow images cannot supply a 3x3 matting neighborhood.
            if min(patch_trimap.shape) < 3:
                continue
            try:
                refined = estimate_alpha_cf(
                    np.ascontiguousarray(rgb[region]), patch_trimap,
                    laplacian_kwargs={'epsilon': 1e-6},
                    cg_kwargs={'maxiter': 250, 'rtol': 1e-5},
                )
            except (ValueError, RuntimeError, np.linalg.LinAlgError) as error:
                # An ambiguous patch must not prevent editing the photo. Keep
                # its model alpha; other patches still benefit from refinement.
                warnings.warn(f'Portrait edge patch retained model alpha: {error}', RuntimeWarning)
                continue
            if not np.isfinite(refined).all():
                continue
            # Feather overlapping estimates rather than leaving tile seams.
            vertical = np.minimum(np.arange(1, bottom - top + 1), np.arange(bottom - top, 0, -1))
            horizontal = np.minimum(np.arange(1, right - left + 1), np.arange(right - left, 0, -1))
            weight = np.minimum(vertical[:, None], horizontal[None, :]).astype(np.float32)
            weight = np.minimum(weight / (overlap / 2), 1)
            total[region] += refined * weight
            weights[region] += weight
    valid = (weights > 0) & unknown
    opacity[valid] = total[valid] / weights[valid]
    opacity[foreground] = 1
    opacity[background] = 0
    return Image.fromarray(np.clip(opacity, 0, 1))
