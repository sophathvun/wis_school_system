# Student Enrollment photo editor

Upload, drop, or paste a JPG/PNG/WebP portrait (up to 10 MB / 24 megapixels).
Use **Edit Photo** to change an existing enrollment photo. The studio provides
3:4 cropping, zoom, rotation, original/edited comparison, brightness, contrast,
color, gentle noise smoothing, sharpening, and Auto Enhance. These controls
adjust existing pixels; they cannot recover missing detail from a severely
blurred original or invent a new face.

**Remove Background** runs a local CPU portrait-matting model. Choose Blue,
White, or a custom color; use Erase/Restore under Refine edges if needed.
Light neutral fabric receives a small automatic contour smoothing pass to reduce
one-pixel teeth from textured backgrounds, keeping dark hair and solid interiors
unchanged. For a local touch-up choose **Smooth edge** under Refine edges and
brush over just the rough outline; each stroke applies gentle feathering.
An image-guided matting pass refines a narrow band around the model outline at
the working photo resolution, using overlapping patches to limit memory use.
This revisits the pale edge around hair/shoulders rather than just blurring it.
Foreground color estimation automatically reduces old background color fringing
around hair and clothing before compositing. It preserves soft edges rather than
shrinking the entire portrait. Upload the original photo again to reprocess a
previously saved edit; colors already flattened into an old edit cannot always
be recovered.
Reset All restores the original photo and adjustments. Cancel leaves the last
accepted photo intact. **Use Photo** prepares the image; save the enrollment to
persist it.

New/replaced enrollment photos are re-encoded on the server as WebP, maximum
40,960 bytes (40 KB). The server starts at 600×800 and reduces quality, then
resolution if necessary, preserving 3:4. Existing photos remain unchanged until
replaced. The enrollment viewer converts stored photos to real JPEG for viewing
and download; the public storage copy stays WebP.

## Local setup

PHP GD with WebP support and Python 3.13 are required. From the project root:

```powershell
python scripts/student-photo-ai/setup.py
```

Setup installs pinned binary packages in
`storage/app/private/student-photo-ai/venv`, downloads the pinned 25.9 MB MODNet
ONNX model, and checks SHA256 before installation. This is the only step needing
internet access. No paid API or cloud account is used. CPU, memory, electricity
and hosting still consume server resources. Model attribution/license are in
`scripts/student-photo-ai/NOTICE.md` and `LICENSE-MODNet`.
PyMatting supplies local foreground edge cleanup. Setup also warms its compilation
cache to avoid delaying the first photo request. Re-run setup after updating the
photo-processing requirements.

The default Python path is the project's virtual environment. Override with
`STUDENT_PHOTO_PYTHON` when needed and refresh Laravel's config cache after
changing it. Install the runtime on each application server; do not put the
model or virtual environment in version control or public storage.

Runtime processing is authenticated, restricted to enrollment create/update/manage
permissions, rate limited, and serialized with a cache lock. Temporary images
stay in private storage and are deleted after success/failure. Uploaded images
are never sent to an external service. If setup is missing or processing fails,
the editor explains the error and allows cropping/enhancement with the original
background. A configured shared cache is needed to serialize processing across
multiple application instances.

JPG downloads use the browser's canvas encoder. They retain the saved portrait's
resolution and may be larger than 40 KB; only the stored WebP has the 40 KB cap.
