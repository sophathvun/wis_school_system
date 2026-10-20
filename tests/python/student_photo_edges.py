"""Regression: changing a blue portrait background must not create blue hair edges."""
from pathlib import Path
import io
import sys
import unittest
import numpy as np
from PIL import Image
from scipy.ndimage import gaussian_filter, maximum_filter

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/student-photo-ai'))
from foreground import clean_foreground
from refine import refine_alpha
from fabric import smooth_fabric_alpha


def rough_portrait_fixture():
    """Known soft outline plus narrow hair detail, with a deliberately coarse halo."""
    height, width = 384, 320
    y, x = np.mgrid[:height, :width].astype(np.float32)
    radius = np.sqrt(((x - width / 2) / 104) ** 2 + ((y - 205) / 161) ** 2)
    alpha = np.clip((1 - radius) * 70 + .5, 0, 1)
    # Fine strands crossing both tile boundaries and the upper hair outline.
    for offset in [-26, -9, 14, 29]:
        strand = np.clip(1.1 - np.abs(x - (width / 2 + offset + (y - 45) * .22)), 0, 1)
        strand[(y < 32) | (y > 100)] = 0
        alpha = np.maximum(alpha, strand)
    foreground = np.empty((height, width, 3), dtype=np.float32)
    foreground[y < 135] = [.08, .07, .06]
    foreground[(y >= 135) & (y < 250)] = [.65, .43, .30]
    foreground[y >= 250] = [.82, .82, .82]
    rgb = np.rint((foreground * alpha[..., None] + (1 - alpha[..., None])) * 255).astype(np.uint8)
    # Simulate model mask upsampling and a 2px falsely-opaque white border.
    low = Image.fromarray(maximum_filter(alpha, size=5)).resize((80, 96), Image.Resampling.BILINEAR)
    rough = np.asarray(low.resize((width, height), Image.Resampling.BILINEAR), dtype=np.float32)
    return Image.fromarray(rgb), foreground, alpha, rough


class PortraitEdgeTests(unittest.TestCase):
    def test_opaque_fabric_contour_removes_fraying_and_retains_hair_detail(self):
        height, width = 180, 140
        y, x = np.mgrid[:height, :width]
        expected = (x < 70).astype(np.float32)
        prior = gaussian_filter(expected, sigma=(0, 1.2))
        refined = (x < 70 + np.where(y % 3 == 0, 2, -2)).astype(np.float32)
        # A translucent wisp outside the shoulder is retained wall, not fabric.
        refined[(x >= 72) & (x < 76) & (y >= 70)] = .2
        rgb = np.full((height, width, 3), [.76, .78, .77], dtype=np.float32)
        rgb[(expected == 1) & (y < 60)] = [.08, .07, .06]
        rgb[(expected == 1) & (y >= 60)] = [.92, .93, .94]
        # Dark pattern immediately adjoining the white shirt must not cause a
        # notch or change back to a ragged contour at the color-label boundary.
        rgb[95:101, 55:72] = [.12, .14, .18]
        image = Image.fromarray(np.rint(rgb * 255).astype(np.uint8))
        result = np.asarray(smooth_fabric_alpha(image, Image.fromarray(refined), prior=Image.fromarray(prior)))
        self.assertLess(np.std(result[80:165].sum(axis=1)), .1)
        self.assertLess(result[80:165, 73:].max(), .01)
        np.testing.assert_array_equal(result[:40], refined[:40])
        np.testing.assert_array_equal(result[:, :60], refined[:, :60])
        self.assertGreater(result[95:101, 68].min(), .99)

    def test_coherent_refined_shirt_edge_can_correct_wrong_model_prior(self):
        height, width = 384, 320
        y, x = np.mgrid[:height, :width].astype(np.float32)
        expected = np.clip(x - (75 + .0015 * (y - 180) ** 2) + .5, 0, 1)
        subject = np.full((height, width, 3), [.79, .83, .79], dtype=np.float32)
        wall = np.full_like(subject, [.83, .87, .83])
        noise = np.random.default_rng(45).normal(0, .003, (height, width, 1))
        pixels = np.rint(np.clip(subject * expected[..., None] + wall * (1 - expected[..., None]) + noise, 0, 1) * 255).astype(np.uint8)
        image = Image.fromarray(pixels)
        prior = maximum_filter(expected, size=19)
        refined = refine_alpha(image, Image.fromarray(prior))
        result = np.asarray(smooth_fabric_alpha(image, refined, prior=Image.fromarray(prior)))
        self.assertLess(np.abs(result - expected).mean(), np.abs(prior - expected).mean() * .3)
        self.assertGreater(result[80:300, 140:280].min(), .99)

    def test_prior_contour_handles_empty_solid_and_colored_masks(self):
        source = Image.new('RGB', (32, 40), (20, 80, 220))
        for value in [0., .5, 1.]:
            alpha = np.full((40, 32), value, dtype=np.float32)
            result = smooth_fabric_alpha(source, Image.fromarray(alpha), prior=Image.fromarray(alpha))
            np.testing.assert_array_equal(np.asarray(result), alpha)

    def test_reconsiders_falsely_opaque_wall_in_low_contrast_shirt_mask(self):
        height, width = 384, 320
        y, x = np.mgrid[:height, :width].astype(np.float32)
        boundary = 75 + .0015 * (y - 180) ** 2
        expected = np.clip(x - boundary + .5, 0, 1)
        subject = np.full((height, width, 3), [.79, .83, .79], dtype=np.float32)
        wall = np.full_like(subject, [.83, .87, .83])
        noise = np.random.default_rng(45).normal(0, .003, (height, width, 1))
        pixels = np.rint(np.clip(subject * expected[..., None] + wall * (1 - expected[..., None]) + noise, 0, 1) * 255).astype(np.uint8)
        image = Image.fromarray(pixels)
        # Nine-pixel wall strip is incorrectly classified as FULLY opaque by
        # the model, so merely smoothing fractional alpha cannot remove it.
        rough = maximum_filter(expected, size=19)
        refined = np.asarray(refine_alpha(image, Image.fromarray(rough)))
        self.assertLess(np.abs(refined - expected).mean(), np.abs(rough - expected).mean() * .3)
        self.assertLess(np.count_nonzero((refined > .9) & (expected < .01)), 150)
        self.assertGreater(refined[80:300, 140:280].min(), .99)

    def test_smooths_light_fabric_teeth_without_smoothing_dark_hair(self):
        height, width = 180, 140
        y, x = np.mgrid[:height, :width]
        edge = 70 + np.where(y % 3 == 0, 1, -1)
        alpha = (x < edge).astype(np.float32)
        rgb = np.full((height, width, 3), [.76, .78, .77], dtype=np.float32)
        rgb[(alpha == 1) & (y < 60)] = [.08, .07, .06]
        rgb[(alpha == 1) & (y >= 60)] = [.92, .93, .94]
        image = Image.fromarray(np.rint(rgb * 255).astype(np.uint8))
        smoothed = np.asarray(smooth_fabric_alpha(image, Image.fromarray(alpha)))
        before = alpha[75:165].sum(axis=1)
        after = smoothed[75:165].sum(axis=1)
        self.assertLess(np.std(after), np.std(before) * .5)
        self.assertLess(abs(after.mean() - before.mean()), 1)
        np.testing.assert_array_equal(smoothed[:50], alpha[:50])
        np.testing.assert_array_equal(smoothed[:, :60], alpha[:, :60])
        np.testing.assert_array_equal(smoothed[:, 80:], alpha[:, 80:])
        self.assertTrue(np.any((smoothed[75:165] > 0) & (smoothed[75:165] < 1)))

    def test_fabric_smoothing_does_not_change_empty_solid_or_colored_masks(self):
        source = Image.new('RGB', (32, 40), (20, 80, 220))
        for value in [0., 1.]:
            alpha = np.full((40, 32), value, dtype=np.float32)
            np.testing.assert_array_equal(np.asarray(smooth_fabric_alpha(source, Image.fromarray(alpha))), alpha)
        alpha = np.zeros((40, 32), dtype=np.float32)
        alpha[:, :17] = 1
        alpha[::3, 17] = 1
        np.testing.assert_array_equal(np.asarray(smooth_fabric_alpha(source, Image.fromarray(alpha))), alpha)

    def test_refines_near_white_shirt_edges_from_a_compressed_jpeg(self):
        _, foreground, expected, rough = rough_portrait_fixture()
        foreground[250:] = .96
        pixels = np.rint((foreground * expected[..., None] + 1 - expected[..., None]) * 255).astype(np.uint8)
        buffer = io.BytesIO()
        Image.fromarray(pixels).save(buffer, format='JPEG', quality=92)
        buffer.seek(0)
        with Image.open(buffer) as jpeg:
            refined = np.asarray(refine_alpha(jpeg.convert('RGB'), Image.fromarray(rough)))
        shirt = np.s_[250:, :]
        self.assertLess(np.abs(refined[shirt] - expected[shirt]).mean(), np.abs(rough[shirt] - expected[shirt]).mean() * .3)
        self.assertGreater(refined[300:340, 140:180].min(), .99)

    def test_refines_coarse_halo_on_blue_and_restores_fine_hair(self):
        source, foreground, expected_alpha, rough = rough_portrait_fixture()
        refined = refine_alpha(source, Image.fromarray(rough))
        result = np.asarray(clean_foreground(source, refined)).astype(np.float32) / 255
        previous = np.asarray(clean_foreground(source, Image.fromarray(rough))).astype(np.float32) / 255
        blue = np.array([.12, .38, .74])
        expected = foreground * expected_alpha[..., None] + blue * (1 - expected_alpha[..., None])
        before = previous[..., :3] * previous[..., 3:] + blue * (1 - previous[..., 3:])
        after = result[..., :3] * result[..., 3:] + blue * (1 - result[..., 3:])
        band = (maximum_filter(expected_alpha, size=15) > 0) & (expected_alpha < 1)
        self.assertLess(np.abs(after - expected)[band].mean(), np.abs(before - expected)[band].mean() * .5)
        refined_values = np.asarray(refined)
        self.assertLess(np.abs(refined_values - expected_alpha).mean(), np.abs(rough - expected_alpha).mean() * .4)
        # Thin hairs should remain translucent instead of a clipped hard edge.
        hair = (expected_alpha > .1) & (expected_alpha < .9) & (np.indices(expected_alpha.shape)[0] < 70)
        self.assertLess(np.abs(refined_values[hair] - expected_alpha[hair]).mean(), .12)
        # The subject crosses the 192px tile stride: both sides must stay accurate.
        seam = np.s_[180:205, :]
        self.assertLess(np.abs(refined_values[seam] - expected_alpha[seam]).mean(), .02)

    def test_refinement_handles_empty_and_solid_masks(self):
        source = Image.new('RGB', (32, 40), (40, 80, 120))
        for value in [0., 1.]:
            alpha = np.full((40, 32), value, dtype=np.float32)
            np.testing.assert_array_equal(np.asarray(refine_alpha(source, Image.fromarray(alpha))), alpha)

    def test_removes_blue_contamination_on_white_without_eroding_soft_edges(self):
        height, width = 192, 144
        y, x = np.mgrid[:height, :width].astype(np.float32)
        radius = np.sqrt(((x - width / 2) / 46) ** 2 + ((y - height / 2) / 72) ** 2)
        alpha = np.clip((1.04 - radius) / .08, 0, 1)
        # Hair, skin and white shirt with known ground-truth foreground colors.
        foreground = np.empty((height, width, 3), dtype=np.float32)
        foreground[y < 68] = [.08, .07, .06]
        foreground[(y >= 68) & (y < 124)] = [.65, .43, .30]
        foreground[y >= 124] = [.85, .85, .85]
        blue = np.array([.03, .48, .83], dtype=np.float32)
        source = np.rint((foreground * alpha[..., None] + blue * (1 - alpha[..., None])) * 255).astype(np.uint8)
        result = np.asarray(clean_foreground(Image.fromarray(source), Image.fromarray(alpha)))
        corrected = result[..., :3].astype(np.float32) / 255
        opacity = result[..., 3].astype(np.float32) / 255
        edge = (alpha > .15) & (alpha < .85)
        expected = foreground * opacity[..., None] + (1 - opacity[..., None])
        before = source / 255 * opacity[..., None] + (1 - opacity[..., None])
        after = corrected * opacity[..., None] + (1 - opacity[..., None])
        before_error = np.abs(before - expected)[edge].mean()
        after_error = np.abs(after - expected)[edge].mean()
        self.assertLess(after_error, before_error * .4)
        self.assertLess(np.abs(opacity[edge] - alpha[edge]).max(), .005)
        np.testing.assert_array_equal(result[..., :3][alpha == 1], source[alpha == 1])
        self.assertTrue(np.all(result[..., 3][alpha == 0] == 0))
        self.assertTrue(np.all(result[..., 3][alpha == 1] == 255))

    def test_preserves_fully_opaque_colors_including_blue_clothing(self):
        source = np.empty((16, 16, 3), dtype=np.uint8)
        source[:] = [20, 80, 220]
        result = np.asarray(clean_foreground(Image.fromarray(source), Image.fromarray(np.ones((16, 16), dtype=np.float32))))
        np.testing.assert_array_equal(result[..., :3], source)
        self.assertTrue(np.all(result[..., 3] == 255))

    def test_empty_matte_is_fully_transparent(self):
        source = Image.new('RGB', (16, 16), (20, 80, 220))
        result = np.asarray(clean_foreground(source, Image.fromarray(np.zeros((16, 16), dtype=np.float32))))
        self.assertTrue(np.all(result[..., 3] == 0))


if __name__ == '__main__':
    unittest.main()
