"""Light fabric needs a clean soft contour, rather than wall-texture detail."""
import numpy as np
from PIL import Image
from scipy.ndimage import distance_transform_edt, gaussian_filter, maximum_filter, median_filter, minimum_filter


def smooth_fabric_alpha(image: Image.Image, alpha: Image.Image) -> Image.Image:
    opacity = np.clip(np.asarray(alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    # Sample color from inside the subject, never from the old wall at the edge.
    # Eroding the sample region also excludes isolated, fine hair strands.
    solid = minimum_filter(opacity, size=5, mode='nearest') > .995
    if not solid.any() or np.all(opacity >= .995) or np.all(opacity <= .005):
        return Image.fromarray(opacity)
    distance, nearest = distance_transform_edt(~solid, return_indices=True)
    rgb = np.asarray(image.convert('RGB'), dtype=np.float32) / 255
    inside = rgb[nearest[0], nearest[1]]
    light = inside.mean(axis=2)
    neutral = inside.max(axis=2) - inside.min(axis=2)
    # Only a few pixels at light neutral edges are eligible. Dark hair, colored
    # clothing and the fully opaque interior keep their original alpha.
    boundary = (maximum_filter(opacity, size=5, mode='nearest') > .01) & (minimum_filter(opacity, size=5, mode='nearest') < .99)
    eligible = boundary & (light > .72) & (neutral < .16) & (distance < 8)
    if not eligible.any():
        return Image.fromarray(opacity)
    sigma = np.clip(max(image.size) / 1200, .45, .9)
    # Remove one-pixel teeth, then feather by less than one working pixel.
    # A partial blend avoids rounding off seams/corners or expanding the shirt.
    filtered = gaussian_filter(median_filter(opacity, size=3, mode='nearest'), sigma=sigma, mode='nearest')
    result = opacity.copy()
    result[eligible] = .2 * opacity[eligible] + .8 * filtered[eligible]
    return Image.fromarray(np.clip(result, 0, 1))
