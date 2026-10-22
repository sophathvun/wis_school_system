# Shared photo editor

The same studio is used by Student Enrollment, HRM Staff Management, User
Management, and the personal Edit Profile page. Each includes **Edit Photo**
for its existing picture, upload/drop/paste, all clarity and selection controls,
rotation, and local background removal. Staff and login/profile photos remain
separate, including for linked staff accounts. Cancel preserves the accepted photo.

Student output stays 3:4 (starting at 600×800); staff and user/profile output stays
square (starting at 400×400). All newly saved photos use WebP up to 40 KB, with
resolution reduced if necessary. User Management's enlarged photo viewer and
download convert stored photos to real JPEG. Existing stored files are retained
until replaced. Replacement files are cleaned up when saving fails.

All studios share the already installed local model and its single processing
slot; no additional AI installation is required. Background routes check their
own permissions: staff create/edit, super administrator for User Management,
and an authenticated account for its own Profile. Other editing stays in the browser.

The preview shows **WebP size** in KB and the compressed dimensions. After editing
pauses, the current crop is measured by the same server encoder used for saving;
no preview file is stored and the AI model is not invoked. **Use Photo** verifies
the current size and displays it below the accepted photo as well. Selection
guides and original comparison mode are excluded from the measured crop.

Upload, drop, or paste a JPG/PNG/WebP portrait (up to 10 MB / 24 megapixels).
Use **Edit Photo** to change an existing enrollment photo. The studio provides
3:4 cropping, zoom, rotation, original/edited comparison, exposure, brightness,
contrast, saturation, gentle noise smoothing, sharpening, and Auto Enhance. These controls
adjust existing pixels; they cannot recover missing detail from a severely
blurred original or invent a new face.

Under **Frame**, use **Straighten (degrees)** to enter an exact angle such as
5, 6, or 5.5, or drag the slider. Negative angles turn left; positive angles
turn right (up to 45 degrees each way). The preview automatically crops to fill
the portrait without empty corners. Zoom and drag to adjust the framing.
**Reset angle** clears only straightening; the Left/Right 90-degree buttons
remain available for sideways photos. **Use Photo** includes the rotation.

For a shirt or another specific area, choose **Clarity → Apply adjustments to →
Selected Area**. Paint over the area using the adjustable brush; **Erase selection**
removes unwanted parts. Brush edges blend softly. **Hide Selection** hides the
purple guide so the edit can be judged without the overlay. The guide is never
included in **Use Photo**. Choose **Move photo** to reposition the crop.

The clarity sliders and Auto Enhance apply to the current scope. Whole-photo and
area adjustments are independent and remain applied when switching scopes.
The selection follows zoom, rotation, and repositioning. **Clear Selection**
removes the area mask; **Reset Area Adjustments** clears only its slider values.
**Reset All** clears both. Selection editing runs in the browser, without another
AI model, server processing slot, or paid service.

**Exposure** ranges from -2.00 to +2.00 EV in 0.01 steps, applying light changes
in linear RGB before brightness, contrast, and saturation. **Saturation** (formerly
Color) ranges from -100 (black and white) through 0 (unchanged) to +100 (stronger
color). Both work independently for Whole Photo and Selected Area. Reset Area
Adjustments and Reset All return them to zero; Auto Enhance also resets exposure
in its current scope. Whole-photo exposure preserves the chosen replacement
background color.

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
shrinking the entire portrait. Color reconstruction uses the soft matte from
before fabric contour smoothing, so making a shoulder edge opaque does not lock
the old background color into the shirt. The final contour and fully opaque
interior colors remain unchanged. No model or dependency reinstall is needed
when deploying this correction. Upload the original photo again to reprocess a
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
