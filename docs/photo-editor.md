# Photo Editor

Open **Photo Editor** from the main menu, choose/drop/paste a JPG, PNG or WebP (up to 10 MB), edit it, and click **Use Photo**. The edited preview provides **Download JPG**, **Download WebP**, **Edit Photo**, and **Clear**.

This page reuses the Student Photo Studio controls, styling and background service: portrait crop, zoom, 90-degree rotation and fine straightening, original comparison, background replacement, edge brushes, whole-photo/selected-area adjustments, exposure, brightness, contrast, saturation, noise smoothing, sharpening, auto enhance and reset. WebP size is shown below the preview. WebP downloads use the existing encoder and are at most 40 KB; JPG downloads use quality 95 and are not subject to that size limit.

The editor does not update student/staff/user records or retain downloaded images. Background processing still requires the existing local photo AI runtime/model on the server.

The main editor and accepted preview display both **WebP size** and **JPG size** in KB. JPG measurement uses the same quality-95, 300-DPI encoder as the download, so the displayed size matches the downloaded file. Measurements do not store preview files.

## Photo sizes

In the main Photo Editor, choose **Photo Size** in the Frame section: **3 × 4 cm**, **3.5 × 4.5 cm**, **4 × 6 cm**, **4.5 × 4.5 cm**, **600 × 800 px** (approximately **5.08 × 6.77 cm** at 300 DPI), or **Customize**. Custom width and height support 1–10 cm in 0.1 cm increments, including landscape. The crop updates immediately while retaining the photo adjustments. Reposition the photo to suit the new frame, then click **Use Photo**. The chosen size appears beneath the accepted preview.

JPG files embed **300 DPI** and use centimetre dimensions rounded to the nearest pixel. WebP downloads preserve those same pixel dimensions; WebP viewers may require choosing the physical print dimensions manually. WebP compression stays within 40 KB without reducing the selected dimensions. If that is impossible for a detailed image, JPG stays available and the preview explains why WebP is unavailable. Student, staff and profile uploads retain their existing fixed formats.

## Natural portrait tools

Start with **Natural**, **Studio Light**, or **Warm Portrait** for gentle adjustments. **Neutral** and **Reset Adjustments** clear adjustments for the current scope while preserving crop, position and background. **View Original** compares the original crop with the edited version.

**Shadows** brightens or darkens darker areas; **Highlights** adjusts brighter areas (lower it for an overly bright shirt). **Warmth** corrects a cool/warm cast, **Tint** corrects green/magenta, and **Vibrance** adjusts muted colors more gently than saturation. **Portrait smoothing** softly blends small texture variations while retaining strong edges. For skin or shirt touch-ups, select **Selected Area**, paint the desired region, and adjust the sliders. These are photographic adjustments, not generated facial details or reshaping.

**Show alignment guides** adds a preview-only thirds grid for positioning and straightening. Guides and purple selection marks are never included in the saved photo or download. These controls are shared by the student, staff and profile photo studios as well as the main Photo Editor.

## Deployment and access

Run `php artisan migrate --force` and `npm run build` during normal deployment. The additive migration registers `photo-editor.view` without granting it to other users. In the permission page, enable the **Photo Editor** main-menu permission for departments, roles or users who should have access. It covers the editor and its processing/download routes. Super administrators already have access.
