Student photo background removal uses MODNet (ZHKKKe/MODNet), converted to ONNX by Xenova.

- Original: https://github.com/ZHKKKe/MODNet
- Model: https://huggingface.co/Xenova/modnet
- Pinned revision: fa2fa546052fba4c08921230a26cc69a333fca12
- SHA256: 07c308cf0fc7e6e8b2065a12ed7fc07e1de8febb7dc7839d7b7f15dd66584df9
- License: Apache License 2.0; see LICENSE-MODNet.

The original model is used without modification. The application performs its own
input resizing/normalization and matte compositing. The model authors do not
endorse this application. Python dependency licenses remain in the isolated
environment's installed distribution metadata.

Boundary alpha and foreground edge colors are estimated with PyMatting (MIT license):
https://github.com/pymatting/pymatting. See LICENSE-PyMatting. This removes old
background color from semi-transparent edges before compositing onto a new color.
Closed-form matting refines the outline from the original image in overlapping
patches; the MODNet weights remain unchanged.
