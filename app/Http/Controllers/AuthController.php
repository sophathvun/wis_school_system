<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use App\Models\Feedback;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController
{
    public function setupForm()
    {
        abort_if(User::query()->exists(), 404);
        return view('auth.setup-admin');
    }

    public function setupAdmin(Request $request)
    {
        abort_if(User::query()->exists(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);
        DB::transaction(function () use ($data) {
            $user = User::create($data + ['status' => 1, 'login_identifier' => 'both', 'is_global' => true]);
            $role = Role::where('code', 'super-admin')->firstOrFail();
            DB::table('access_user_roles')->insert([
                'user_id' => $user->id, 'role_id' => $role->id, 'campus_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('login')->with('success', 'Super Administrator account created. You can now sign in.');
    }

    public function loginForm() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'login_by' => ['required', 'in:username,email'],
        ]);
        $user = User::where($data['login_by'], $data['identifier'])->where('status', 1)->first();
        $loginErrors = [];
        if (!$user || !in_array($user->login_identifier, [$data['login_by'], 'both'], true)) {
            $loginErrors['identifier'] = $data['login_by'] === 'email'
                ? 'The email address is incorrect or is not registered.'
                : 'The username is incorrect or is not registered.';
        }
        if (!$user || !Hash::check($data['password'], $user->password)) {
            $loginErrors['password'] = 'The password is incorrect.';
        }
        if (!$loginErrors) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            $request->session()->forget('url.intended');

            return $user->must_change_password
                ? redirect()->to(route('profile') . '#profile-edit')->with('warning', 'Please change your temporary password before continuing.')
                : redirect()->route('dashboard');
        }
        return back()->withErrors($loginErrors)->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function forgotForm() { return view('auth.forgot-password'); }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($data);
        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => null, 'must_change_password' => false])->save();
        });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset successfully. You can now sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load(['department', 'position', 'campuses', 'roles']);
        $this->ensurePublicCardToken($user);
        $dashboardPermissionCampusId = $user->active_campus_id
            ?: $user->accessibleCampuses()->value('tb_school_info.id');

        return view('profile', [
            'profileUser' => $user,
            'branding' => BrandingSetting::current(),
            'publicCardUrl' => route('staff-card.public', $user->public_card_token),
            'publicCardQrUrl' => route('staff-card.qr', $user->public_card_token),
            'publicCardVcardUrl' => route('staff-card.vcard', $user->public_card_token),
            'canCustomizeDashboard' => $user->hasPermission('dashboard.customize', $dashboardPermissionCampusId),
        ]);
    }

    public function status(Request $request) { return view('profile-status'); }

    public function feedbackForm()
    {
        return view('auth.feedback');
    }

    public function feedback(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'required_without:attachment', 'string', 'max:50000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('feedback', 'public');
        }

        $message = $this->sanitizeFeedbackMessage($data['message'] ?? '');

        Feedback::create([
            'user_id' => $request->user()?->id,
            'subject' => $data['subject'],
            'message' => $message !== '' ? $message : '<p>No message provided.</p>',
            'attachment_path' => $attachmentPath,
            'status' => 'new',
        ]);

        return back()->with('success', 'Thank you. Your feedback has been submitted.');
    }

    public function feedbackList(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdmin() || $user?->hasPermission('communication.view', $user->active_campus_id) || $user?->hasPermission('feedback.view', $user->active_campus_id), 403);

        $feedbackItems = Feedback::with(['user.department'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('auth.feedback-list', compact('feedbackItems'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $requiresPasswordChange = (bool) $user->must_change_password;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:Male,Female,Other,male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'login_identifier' => ['required', 'in:username,email,both'],
            'password' => [$requiresPasswordChange ? 'required' : 'nullable', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
        if (array_key_exists('gender', $data)) {
            $data['gender'] = $this->normalizeGender($data['gender'] ?? null);
        }
        $user->fill(collect($data)->except(['password', 'photo'])->toArray());
        if (!empty($data['password'])) {
            $user->password = $data['password'];
            $user->must_change_password = false;
        }
        if ($request->hasFile('photo')) $user->photo_path = $request->file('photo')->store('users', 'public');
        $user->save();

        if ($requiresPasswordChange && !$user->must_change_password) {
            return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    private function normalizeGender(?string $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            default => null,
        };
    }

    public function updateNameCard(Request $request)
    {
        $user = $request->user();
        $this->ensurePublicCardToken($user);

        $user->forceFill([
            'public_card_enabled' => $request->has('public_card_enabled')
                ? $request->boolean('public_card_enabled')
                : (bool) $user->public_card_enabled,
            'public_card_orientation' => $request->validate([
                'public_card_orientation' => ['nullable', 'in:portrait,landscape'],
                'public_card_background' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ])['public_card_orientation'] ?? ($user->public_card_orientation ?: 'landscape'),
            'public_card_background' => $request->input('public_card_background', $user->public_card_background ?: '#206bc4'),
        ])->save();

        return redirect()->to(route('profile') . '#name-card')
            ->with('success', 'Name card setting updated successfully.');
    }

    public function regenerateNameCard(Request $request)
    {
        $request->user()->forceFill([
            'public_card_token' => $this->newPublicCardToken(),
            'public_card_enabled' => true,
            'public_card_scan_count' => 0,
            'public_card_last_viewed_at' => null,
        ])->save();

        return back()->with('success', 'Name card QR code regenerated successfully.');
    }

    public function publicStaffCard(string $token)
    {
        $staff = $this->publicStaffByToken($token);

        $staff->forceFill([
            'public_card_scan_count' => ((int) $staff->public_card_scan_count) + 1,
            'public_card_last_viewed_at' => now(),
        ])->save();

        return view('staff-card-public', [
            'staff' => $staff,
            'branding' => BrandingSetting::current(),
            'publicCardUrl' => route('staff-card.public', $staff->public_card_token),
            'publicCardVcardUrl' => route('staff-card.vcard', $staff->public_card_token),
            'publicCardQrUrl' => route('staff-card.qr', $staff->public_card_token),
            'publicCardShareImageUrl' => route('staff-card.share-image', $staff->public_card_token),
        ]);
    }

    public function staffCardShareImage(string $token)
    {
        $staff = $this->publicStaffByToken($token);

        return response($this->staffCardPng($staff, BrandingSetting::current()), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function staffCardQr(string $token)
    {
        $staff = $this->publicStaffByToken($token);

        return response($this->qrSvg(route('staff-card.public', $staff->public_card_token)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function staffCardVcard(string $token)
    {
        $staff = $this->publicStaffByToken($token);
        $filename = Str::slug($staff->name ?: 'staff-contact') . '.vcf';

        return response($this->staffVcard($staff), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function sanitizeFeedbackMessage(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><span><div>');
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        $html = preg_replace('/<span([^>]*)style\s*=\s*"([^"]*)"([^>]*)>/i', function ($matches) {
            return $this->cleanFeedbackSpan($matches[2]);
        }, $html) ?? $html;
        $html = preg_replace("/<span([^>]*)style\s*=\s*'([^']*)'([^>]*)>/i", function ($matches) {
            return $this->cleanFeedbackSpan($matches[2]);
        }, $html) ?? $html;
        $html = preg_replace('/<span(?!\s+style=)[^>]*>/i', '<span>', $html) ?? $html;
        $html = preg_replace('/<(p|div|ul|ol|li|strong|b|em|i|u)\b[^>]*>/i', '<$1>', $html) ?? $html;
        $html = preg_replace('/<span>\s*<\/span>/i', '', $html) ?? $html;

        return trim($html);
    }

    private function cleanFeedbackSpan(string $style): string
    {
        $rules = [];

        if (preg_match('/font-family\s*:\s*([^;]+)/i', $style, $family)) {
            $allowedFonts = [
                'khmer os siemreap' => 'Khmer OS Siemreap',
                'khmer os battambang' => 'Khmer OS Battambang',
                'khmer os muol light' => 'Khmer OS Muol Light',
                'noto sans khmer' => 'Noto Sans Khmer',
                'tacteing' => 'Tacteing',
                'arial' => 'Arial',
                'times new roman' => 'Times New Roman',
            ];
            $fontValue = strtolower(trim(str_replace(['"', "'"], '', $family[1])));
            if (isset($allowedFonts[$fontValue])) {
                $rules[] = 'font-family: ' . $allowedFonts[$fontValue];
            }
        }

        if (preg_match('/font-size\s*:\s*(12px|14px|16px|18px|20px)/i', $style, $size)) {
            $rules[] = 'font-size: ' . strtolower($size[1]);
        }

        if (preg_match('/color\s*:\s*(#[0-9a-f]{6})/i', $style, $color)) {
            $rules[] = 'color: ' . strtolower($color[1]);
        } elseif (preg_match('/color\s*:\s*(rgb\(\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*,\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*,\s*(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\s*\))/i', $style, $color)) {
            $rules[] = 'color: ' . strtolower($color[1]);
        }

        return $rules ? '<span style="' . implode('; ', $rules) . '">' : '<span>';
    }
    private function ensurePublicCardToken(User $user): void
    {
        if ($user->public_card_token) {
            return;
        }

        $user->forceFill([
            'public_card_token' => $this->newPublicCardToken(),
            'public_card_enabled' => true,
        ])->save();
    }

    private function newPublicCardToken(): string
    {
        do {
            $token = Str::random(40);
        } while (User::where('public_card_token', $token)->exists());

        return $token;
    }

    private function publicStaffByToken(string $token): User
    {
        return User::with(['department', 'position', 'campuses'])
            ->where('public_card_token', $token)
            ->where('public_card_enabled', true)
            ->where('status', 1)
            ->firstOrFail();
    }

    private function qrSvg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(360),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($value);
    }

    private function qrPng(string $value, int $size = 330): string
    {
        return (new Writer(new GDLibRenderer($size, 2, 'png', 6)))->writeString($value);
    }

    private function staffCardPng(User $staff, BrandingSetting $branding): string
    {
        $width = 1200;
        $height = 1600;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $this->fillGradient($image, $width, $height, [230, 242, 251], [222, 240, 232]);

        $cardX = 82;
        $cardY = 70;
        $cardW = 1036;
        $cardH = 1370;
        $navy = $this->gdColor($image, 32, 58, 95);
        $muted = $this->gdColor($image, 91, 112, 137);
        $blue = $this->gdColor($image, 32, 107, 196);
        $white = $this->gdColor($image, 255, 255, 255);
        $soft = $this->gdColor($image, 247, 250, 253);

        $this->roundedRectangle($image, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, 66, $white);

        $font = public_path('fonts/khmer/KhmerOSsiemreap.ttf');
        $logoPath = $this->publicStoragePath($branding->report_logo_1_path ?? $branding->login_logo_path ?? $branding->sidebar_logo_path ?? null);
        if ($logoPath) {
            $logo = $this->loadGdImage($logoPath);
            if ($logo) {
                $this->copyContain($image, $logo, $cardX + 285, $cardY + 100, 470, 150);
                imagedestroy($logo);
            }
        }

        $photoX = $cardX + 145;
        $photoY = $cardY + 360;
        $photoW = 220;
        $photoH = 285;
        $this->roundedRectangle($image, $photoX, $photoY, $photoX + $photoW, $photoY + $photoH, 38, $soft);
        $photoPath = $this->publicStoragePath($staff->photo_path);
        if ($photoPath) {
            $photo = $this->loadGdImage($photoPath);
            if ($photo) {
                $this->copyCover($image, $photo, $photoX, $photoY, $photoW, $photoH);
                imagedestroy($photo);
            }
        } else {
            $this->drawCenteredText($image, mb_substr($staff->name ?: 'S', 0, 1), $photoX, $photoY + 92, $photoW, $font, 88, $navy);
        }

        $textX = $cardX + 430;
        $textY = $cardY + 330;
        $name = mb_strtoupper($staff->name ?: 'Staff Name');
        $nameLines = $this->wrapText($name, $font, 72, 500);
        foreach (array_slice($nameLines, 0, 2) as $line) {
            $this->drawText($image, $line, $textX, $textY, $font, 72, $navy);
            $textY += 80;
        }

        $position = $staff->position?->name ?: $staff->department?->name ?: 'Staff / Teacher';
        $this->drawText($image, $position, $textX, $textY + 8, $font, 38, $muted);
        $textY += 88;

        if ($staff->phone) {
            $this->drawText($image, '☏  ' . $staff->phone, $textX, $textY, $font, 34, $blue);
            $textY += 50;
        }

        if ($staff->email) {
            foreach (array_slice($this->wrapText('✉  ' . $staff->email, $font, 34, 560), 0, 2) as $line) {
                $this->drawText($image, $line, $textX, $textY, $font, 34, $blue);
                $textY += 44;
            }
        }

        $qrPng = $this->qrPng(route('staff-card.public', $staff->public_card_token), 420);
        $qr = imagecreatefromstring($qrPng);
        if ($qr) {
            imagecopyresampled($image, $qr, $cardX + 330, $cardY + 790, 0, 0, 380, 380, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        $this->drawCenteredText($image, 'Scan', $cardX, $cardY + 1240, $cardW, $font, 38, $navy);
        $link = route('staff-card.public', $staff->public_card_token);
        $this->drawCenteredText($image, $link, $cardX + 90, $cardY + 1315, $cardW - 180, $font, 24, $muted);

        ob_start();
        imagepng($image, null, 6);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function gdColor($image, int $red, int $green, int $blue, int $alpha = 0): int
    {
        return imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
    }

    private function fillGradient($image, int $width, int $height, array $from, array $to): void
    {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $red = (int) round($from[0] + ($to[0] - $from[0]) * $ratio);
            $green = (int) round($from[1] + ($to[1] - $from[1]) * $ratio);
            $blue = (int) round($from[2] + ($to[2] - $from[2]) * $ratio);
            imageline($image, 0, $y, $width, $y, $this->gdColor($image, $red, $green, $blue));
        }
    }

    private function roundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function publicStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $fullPath = storage_path('app/public/' . ltrim($path, '/\\'));

        return is_file($fullPath) ? $fullPath : null;
    }

    private function loadGdImage(string $path)
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return @imagecreatefromstring($contents) ?: null;
    }

    private function copyContain($canvas, $source, int $x, int $y, int $w, int $h): void
    {
        $sourceW = imagesx($source);
        $sourceH = imagesy($source);
        $scale = min($w / $sourceW, $h / $sourceH);
        $targetW = (int) round($sourceW * $scale);
        $targetH = (int) round($sourceH * $scale);
        $targetX = $x + (int) (($w - $targetW) / 2);
        $targetY = $y + (int) (($h - $targetH) / 2);
        imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetW, $targetH, $sourceW, $sourceH);
    }

    private function copyCover($canvas, $source, int $x, int $y, int $w, int $h): void
    {
        $sourceW = imagesx($source);
        $sourceH = imagesy($source);
        $scale = max($w / $sourceW, $h / $sourceH);
        $cropW = (int) round($w / $scale);
        $cropH = (int) round($h / $scale);
        $cropX = max(0, (int) (($sourceW - $cropW) / 2));
        $cropY = max(0, (int) (($sourceH - $cropH) / 2));
        imagecopyresampled($canvas, $source, $x, $y, $cropX, $cropY, $w, $h, $cropW, $cropH);
    }

    private function drawText($image, string $text, int $x, int $y, string $font, int $size, int $color): void
    {
        if (is_file($font)) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }

        imagestring($image, 5, $x, $y - 16, $text, $color);
    }

    private function drawCenteredText($image, string $text, int $x, int $y, int $width, string $font, int $size, int $color): void
    {
        if (is_file($font)) {
            $box = imagettfbbox($size, 0, $font, $text);
            $textWidth = $box ? abs($box[2] - $box[0]) : 0;
            imagettftext($image, $size, 0, $x + (int) (($width - $textWidth) / 2), $y, $color, $font, $text);
            return;
        }

        imagestring($image, 5, $x + (int) (($width - strlen($text) * 9) / 2), $y - 16, $text, $color);
    }

    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $test = trim($line . ' ' . $word);
            if ($line !== '' && $this->textWidth($test, $font, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [$text];
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        if (is_file($font)) {
            $box = imagettfbbox($size, 0, $font, $text);

            return $box ? abs($box[2] - $box[0]) : strlen($text) * $size;
        }

        return strlen($text) * 9;
    }

    private function staffVcard(User $staff): string
    {
        $campus = $staff->campuses->pluck('campus_name_en')->filter()->join(', ');
        $organization = trim('Western International School' . ($campus ? ' - ' . $campus : ''));

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->vcardValue($staff->name),
            'ORG:' . $this->vcardValue($organization),
            'TITLE:' . $this->vcardValue($staff->position?->name ?: $staff->department?->name),
        ];

        if ($staff->phone) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . $this->vcardValue($staff->phone);
        }

        if ($staff->email) {
            $lines[] = 'EMAIL;TYPE=WORK:' . $this->vcardValue($staff->email);
        }

        $lines[] = 'URL:' . route('staff-card.public', $staff->public_card_token);
        $lines[] = 'NOTE:' . $this->vcardValue(trim(($staff->department?->name ?: '') . ($campus ? ' | ' . $campus : '')));
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function vcardValue(?string $value): string
    {
        return str_replace(["\\", "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], trim((string) $value));
    }
}

