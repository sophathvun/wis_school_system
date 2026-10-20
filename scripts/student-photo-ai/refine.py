"""Refine the model's coarse boundary using the original portrait pixels."""
import warnings
import numpy as np
from PIL import Image
from scipy.ndimage import distance_transform_edt, gaussian_filter, maximum_filter, minimum_filter
from pymatting import estimate_alpha_cf


def refine_alpha(image: Image.Image, alpha: Image.Image) -> Image.Image:
    opacity = np.clip(np.asarray(alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    height, width = opacity.shape
    radius = max(2, min(4, round(max(image.size) / 400)))
    # Reconsider both sides of the prediction; model confidence alone cannot
    # prove that a light wall pixel belongs to a similarly colored shirt.
    foreground = minimum_filter(opacity, size=2 * radius + 1, mode='nearest') > .98
    background = maximum_filter(opacity, size=2 * radius + 1, mode='nearest') < .02
    if not foreground.any() or not background.any():
        return Image.fromarray(opacity)

    rgb = np.asarray(image.convert('RGB'), dtype=np.float32) / 255.0
    # A 2px band left falsely-opaque wall locked into the shirt. Compare colors
    # well INSIDE the subject and well OUTSIDE the prediction, then widen only
    # ambiguous light/neutral boundaries. Hair retains the narrower trimap.
    wide_radius = max(8, min(24, round(max(image.size) / 40)))
    inner = minimum_filter(opacity, size=2 * wide_radius + 1, mode='nearest') > .98
    outer = maximum_filter(opacity, size=2 * wide_radius + 1, mode='nearest') < .02
    low_contrast = np.zeros_like(foreground)
    if inner.any() and outer.any():
        nearest_inner = distance_transform_edt(~inner, return_distances=False, return_indices=True)
        nearest_outer = distance_transform_edt(~outer, return_distances=False, return_indices=True)
        # Limit temporary RGB arrays for small-memory application servers.
        for start in range(0, height, 128):
            rows = np.s_[start:min(start + 128, height)]
            subject_color = rgb[nearest_inner[0, rows], nearest_inner[1, rows]]
            wall_color = rgb[nearest_outer[0, rows], nearest_outer[1, rows]]
            low_contrast[rows] = ((subject_color.mean(axis=2) > .65)
                                  & (np.ptp(subject_color, axis=2) < .20)
                                  & (np.sqrt(np.mean((subject_color - wall_color) ** 2, axis=2)) < .14))
        del nearest_inner, nearest_outer, subject_color, wall_color
        foreground[low_contrast] = inner[low_contrast]
        background[low_contrast] = outer[low_contrast]
    unknown = ~(foreground | background)
    if not unknown.any():
        return Image.fromarray(opacity)
    # In the ambiguous band, reduce fine wall/JPEG noise in the solver's color
    # guidance. The output RGB still comes from the original photo.
    if np.any(low_contrast & unknown):
        denoised = gaussian_filter(rgb, sigma=(.6, .6, 0), mode='nearest')
        rgb[low_contrast] = denoised[low_contrast]
        del denoised
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
                    np.ascontiguousarray(rgb[region], dtype=np.float64), patch_trimap,
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
