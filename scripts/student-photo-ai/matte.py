"""MODNet inference only: no network, no face generation, no persistent image cache."""
import argparse
import hashlib
from pathlib import Path
import numpy as np
import onnxruntime as ort
from PIL import Image, ImageOps
from foreground import clean_foreground
from refine import refine_alpha
from fabric import smooth_fabric_alpha

Image.MAX_IMAGE_PIXELS = 24_000_000
SHA256 = '07c308cf0fc7e6e8b2065a12ed7fc07e1de8febb7dc7839d7b7f15dd66584df9'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('model', type=Path)
    parser.add_argument('source', type=Path)
    parser.add_argument('destination', type=Path)
    args = parser.parse_args()
    if hashlib.sha256(args.model.read_bytes()).hexdigest() != SHA256:
        raise RuntimeError('Model integrity check failed.')
    with Image.open(args.source) as source:
        if source.width * source.height > Image.MAX_IMAGE_PIXELS:
            raise ValueError('Image is too large.')
        image = ImageOps.exif_transpose(source).convert('RGB')
    image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
    scale = min(512 / min(image.size), 1024 / max(image.size))
    size = tuple(max(32, int(value * scale) // 32 * 32) for value in image.size)
    pixels = np.asarray(image.resize(size, Image.Resampling.BILINEAR), dtype=np.float32)
    tensor = np.ascontiguousarray((pixels / 127.5 - 1).transpose(2, 0, 1)[None])
    options = ort.SessionOptions()
    options.intra_op_num_threads = 2
    options.inter_op_num_threads = 1
    options.log_severity_level = 3
    session = ort.InferenceSession(str(args.model), sess_options=options, providers=['CPUExecutionProvider'])
    matte = session.run(None, {session.get_inputs()[0].name: tensor})[0].squeeze()
    # Keep floating-point alpha until foreground estimation is complete.
    alpha = Image.fromarray(np.clip(matte, 0, 1).astype(np.float32))
    refined = smooth_fabric_alpha(image, refine_alpha(image, alpha), prior=alpha)
    clean_foreground(image, refined).save(args.destination, format='PNG')


if __name__ == '__main__':
    main()
