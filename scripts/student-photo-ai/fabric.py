"""Light fabric needs a clean soft contour, rather than wall-texture detail."""
import numpy as np
from PIL import Image
from scipy.ndimage import distance_transform_edt, gaussian_filter, maximum_filter, median_filter, minimum_filter


def _fabric_contours(image: Image.Image, alpha: Image.Image, prior: Image.Image) -> Image.Image:
    opacity = np.clip(np.asarray(alpha.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    prediction = np.clip(np.asarray(prior.resize(image.size, Image.Resampling.BILINEAR), dtype=np.float32), 0, 1)
    if min(opacity.shape) < 3 or np.all(opacity >= .995) or np.all(opacity <= .005):
        return Image.fromarray(opacity)
    # Use the model's solid interior for color samples. The refined matte can
    # contain wall wisps and is not a reliable place to sample shirt colors.
    solid = minimum_filter(prediction, size=7, mode='nearest') > .98
    if not solid.any():
        return Image.fromarray(opacity)
    nearest = distance_transform_edt(~solid, return_distances=False, return_indices=True)
    rgb = np.asarray(image.convert('RGB'), dtype=np.float32) / 255
    garment = np.zeros_like(solid)
    for start in range(0, opacity.shape[0], 128):
        rows = np.s_[start:start + 128]
        colors = rgb[nearest[0, rows], nearest[1, rows]]
        garment[rows] = (colors.mean(axis=2) > .72) & (np.ptp(colors, axis=2) < .16)
    del nearest, rgb, colors
    if not garment.any():
        return Image.fromarray(opacity)
    # Include the dark collar/cuff immediately adjoining light fabric, so a
    # color-label boundary cannot cut a triangular notch into the silhouette.
    radius = max(3, min(32, round(max(image.size) / 50)))
    garment = maximum_filter(garment, size=2 * radius + 1, mode='nearest')
    weight = gaussian_filter(garment.astype(np.float32), sigma=max(.8, max(image.size) / 320), mode='nearest')
    weight[weight < .001] = 0
    weight[weight > .999] = 1

    # A good refined edge can correct a wrong model silhouette. Retain it when
    # it is coherent. When matting invents texture ALONG the fabric contour,
    # fall back to the smoother model geometry for the garment region only.
    py, px = np.gradient(gaussian_filter(prediction, sigma=2, mode='nearest'))
    dy, dx = np.gradient(opacity)
    band = garment & (prediction > .1) & (prediction < .9)
    use_prior = False
    if np.count_nonzero(band) >= 32:
        tangent = np.abs(px[band] * dy[band] - py[band] * dx[band]) / (np.hypot(px[band], py[band]) + 1e-5)
        use_prior = float(tangent.mean()) > .008
    del py, px, dy, dx
    silhouette = (prediction if use_prior else opacity) >= .5
    if silhouette.all() or not silhouette.any():
        return Image.fromarray(opacity)
    # Fabric is opaque: regularize its level-set and use a narrow antialiased
    # boundary, rather than blurring a wide band of translucent wall pixels.
    signed = distance_transform_edt(silhouette) - distance_transform_edt(~silhouette)
    signed = gaussian_filter(signed, sigma=max(.6, min(2., max(image.size) / 1067)), mode='nearest')
    contour = np.clip(.5 + signed / 2, 0, 1).astype(np.float32)
    result = weight * contour + (1 - weight) * opacity
    return Image.fromarray(np.clip(result, 0, 1))


def smooth_fabric_alpha(image: Image.Image, alpha: Image.Image, prior: Image.Image | None = None) -> Image.Image:
    if prior is not None:
        return _fabric_contours(image, alpha, prior)
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
