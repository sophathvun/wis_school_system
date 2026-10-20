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
Fabric contours are treated as opaque with a narrow antialiased boundary.
If matting invents texture along a shirt edge, the fabric region uses the model
silhouette; coherent refined contours are retained so a wrong prediction can
still be corrected. The fabric region includes adjoining collars and cuffs to
avoid notches where the fabric color changes. Soft hair matting stays separate.
An image-guided matting pass refines a narrow band around the model outline at
the working photo resolution, using overlapping patches to limit memory use.
Where light fabric and the old wall have similar colors, the correction band
widens so wrongly opaque wall pixels can be reconsidered. Solver guidance is
lightly denoised in that band; the portrait RGB remains from the original image.
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

The setup must run separately on **each server**, including the production VPS.
Git does not deploy `storage/app/private/student-photo-ai`. Do not transfer the
Windows virtual environment to Linux; create it on Linux using the setup script.
Python environments contain platform-specific binaries and absolute paths.

PHP GD with WebP support and Python 3.12 or newer are required. Python 3.12 is
supported on Ubuntu 24.04; the Windows development runtime uses Python 3.13.
From the project root:

```powershell
python scripts/student-photo-ai/setup.py
```

On Linux, from the deployed application directory with Python 3.12 or newer installed:

```bash
python3 scripts/student-photo-ai/setup.py
php artisan config:clear
php artisan student-photos:check --verify
```

The OS-specific Python installation commands depend on the VPS distribution.
Python needs its `venv`/pip components. Run setup as the deployment/application
user, and give the PHP service user executable/read access to Python, packages,
model and scripts, plus write access to the private runtime directory and Numba
cache. Do not use world-writable permissions. Run the diagnostic as the PHP
service user as well when troubleshooting service-user permissions.

`php artisan student-photos:check` reports missing paths without running Python.
`--verify` additionally tests package imports, model SHA256 and CPU loading, and
temporary private-file creation/removal. It is offline and uses no student photo.
If `STUDENT_PHOTO_PYTHON` is set, it must be an absolute **Linux** executable path
on the VPS, for example `/actual/app/path/storage/app/private/student-photo-ai/venv/bin/python`.
Leave it blank to use the project-local environment. Rebuild the production
configuration cache with `php artisan config:cache` after verification if your
deployment uses cached configuration. A cached Windows path can otherwise
override the Linux default.

For Ubuntu 24.04, install the OS components with `apt install python3-venv
libgomp1 ca-certificates` after `apt update`, then run setup with `python3`.
The system Python does not need replacing. After setup, ensure the PHP service
user owns/writes the runtime cache and temporary directories. On a default
Ubuntu PHP installation this user is `www-data`; custom PHP-FPM pools may use a
different user. Verify with that service user rather than only with root.

Shared hosting that disables `proc_open` or does not allow Python/ONNX CPU
packages cannot run this local background-removal implementation. Cropping,
clarity adjustment, WebP saving, and JPG download remain available.

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
