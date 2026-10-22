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

## Healing, Patch and Blur

The **Healing, Patch & Blur** section is shared by Photo Editor, Student Photo Studio, Staff Photo Studio and the user/profile editors. Retouching runs in the browser with no extra AI service or server installation. Select a labeled icon button (**Off / Move**, **Spot Healing Brush**, **Healing Brush**, **Patch Tool**, or **Blur Tool**); the selected tool is highlighted. Adjust **Brush size**, **Softness** and **Strength**, then work directly on the preview. Start with short strokes and a brush slightly larger than the mark.

- **Spot Healing Brush:** brush over a small blemish, dust speck or small shirt mark. The editor searches nearby texture matching the surrounding area and blends it into the mark. If a source cannot fit, reduce the brush or use Healing Brush.
- **Healing Brush:** click **Set Source**, then tap/click a clean area of similar skin or fabric. Brush over the unwanted mark. Desktop users can also **Alt-click** to set a source. The green cross identifies the source; source texture is frozen during each stroke to avoid copying the repair into itself.
- **Patch Tool:** draw an outline around the mark. Release, then drag from inside the outlined area onto clean texture and release again to apply. The blue outline is the repair area; the green outline shows the source. Keep the source inside the photo. Use **Clear Patch** to start a different selection. Large patches are limited to 500,000 source pixels.
- **Blur Tool:** select the blur icon and brush over an area to soften detail. Brush size controls the area, Softness feathers its edges, and Strength controls how much blur is blended in. No source selection is needed. Each stroke has its own Undo/Redo step and works in the main, student and staff editors. Original comparison, background transparency and preview-only brush markers behave the same as other retouch tools.

**Undo Retouch** and **Redo** step through retouch strokes/patches. History is bounded to 20 operations and about 48 MB, retaining the latest operation. **Reset Retouching** removes retouch edits while keeping framing, background and adjustment settings. **Reset All** resets the entire editing session. Choose **Off — use frame / selection tools** to reposition the photo or paint an adjustment selection.

Retouch edits follow the source image through crop, zoom, rotation and photo-size changes. Existing background cutout transparency is preserved. **View Original** excludes retouching; source crosses, brush circles and patch outlines never appear in size measurements, uploads or downloads. Canceling a touch stroke discards the unfinished stroke. Repairs blend existing nearby texture and color; large changes across hair, logos or strongly different textures may need a smaller patch and a more suitable manual source.

## Deployment and access

Run `php artisan migrate --force` and `npm run build` during normal deployment. The additive migration registers `photo-editor.view` without granting it to other users. In the permission page, enable the **Photo Editor** main-menu permission for departments, roles or users who should have access. It covers the editor and its processing/download routes. Super administrators already have access.

Photo Editor honors an enabled grant from an assigned **active role**, the user's department, or a direct user grant. It is a personal photo workspace, so a role's campus assignment and the selected campus do not restrict it. The menu, page, background processing, size measurements and downloads use the same access check. Inactive roles and users without a grant remain blocked. Other modules retain their existing campus restrictions.

After deploying the role-access fix, clear cached routes with `php artisan route:clear` (or rebuild your route cache as part of deployment). Existing saved Photo Editor grants are retained; users do not need a separate personal grant when their role already allows it.
