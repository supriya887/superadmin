<?php


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

if (!function_exists('user_role_loop')) {

function user_role_loop($roleId, $permId)
{
    if ($roleId === null || $roleId === '' || (int) $roleId <= 0) {
        return [];
    }

    $permission = DB::connection('pgsql_admin')
        ->table('admin_user_role_permission')
        ->select('permission')
        ->where([
            'role_id' => (int) $roleId,
            'module_id' => (int) $permId,
        ])
        ->get()
        ->toArray();

    if (empty($permission)) {
        return [];
    }

    $array = [];
    foreach ($permission as $row) {
        $array[] = $row->permission;
    }
    return $array;
}
function permission($permId, $permission)
{
    // Bypass for super_admin (case-insensitive)
    $userRole = strtolower(trim((string) Session::get('userRole', '')));
    if ($userRole === 'super_admin') {
        return 1;
    }

    $roleId = (int) Session::get('roleId');
    if ($roleId <= 0) {
        return 0;
    }

    $user = DB::connection('pgsql_admin')
        ->table('admin_user_role_permission')
        ->where([
            'role_id' => $roleId,
            'module_id' => (int) $permId,
            'permission' => $permission,
        ])
        ->get()
        ->toArray();

    return count($user) > 0 ? 1 : 0;
}

function setMenu($menuName)
{
    $sessionMenu = Session::get('menu');
    if ($sessionMenu == $menuName) {
        return 'active';
    }
    return "";
}

function form_check($type, $jobcard)
{
    $jobcard_no = Session::get('jobcard_no');
    $type = Session::get('type');

    $Array = array(
        'type' => $type,
        'jobcard_no' => $jobcard,
    );
    $result = DB::table('logistics_jobcard_no')->where($Array)->first();
    if (!empty($result) && $result->jobcard_no == $jobcard_no && $result->type == $type) {
        return 1;
    }

    return 0;
}

function getfinancialYear($date)
{
    $year = date("y", strtotime($date));
    $newYear = date('y', strtotime($date . ' + 1 years'));
    return $year . "-" . $newYear;
}

function to_number($number)
{
    $no = round($number);
    $point = round($number - $no, 2) * 100;
    $hundred = null;
    $digits_1 = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        '0' => '', '1' => 'one', '2' => 'two',
        '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
        '7' => 'seven', '8' => 'eight', '9' => 'nine',
        '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
        '13' => 'thirteen', '14' => 'fourteen',
        '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
        '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
        '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
        '60' => 'sixty', '70' => 'seventy',
        '80' => 'eighty', '90' => 'ninety'
    );
    $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number < 21) ? $words[$number] .
                " " . $digits[$counter] . $plural . " " . $hundred
                :
                $words[floor($number / 10) * 10]
                . " " . $words[$number % 10] . " "
                . $digits[$counter] . $plural . " " . $hundred;
        }
        else
            $str[] = null;
    }
    $str = array_reverse($str);
    $result = implode('', $str);
    $points = ($point) ? 
        " and " . @$words[$point / 10] . " " .
        @$words[$point = $point % 10] : '';
    if ($points) {
        return strtoupper("Rupees  " . $result . $points . " Paise only");
    }
    else {
        return strtoupper("Rupees  " . $result . " only");
    }

}
function numberTowords($num)
{
    $ones = array(0 => "", 1 => "one", 2 => "two", 3 => "three", 4 => "four", 5 => "five", 6 => "six", 7 => "seven", 8 => "eight", 9 => "nine", 10 => "ten", 11 => "eleven", 12 => "twelve", 13 => "thirteen", 14 => "fourteen", 15 => "fifteen", 16 => "sixteen", 17 => "seventeen", 18 => "eighteen", 19 => "nineteen");
    $tens = array(0 => "", 1 => "ten", 2 => "twenty", 3 => "thirty", 4 => "forty", 5 => "fifty", 6 => "sixty", 7 => "seventy", 8 => "eighty", 9 => "ninety");
    $hundreds = array("hundred", "thousand", "million", "billion", "trillion", "quadrillion"); //limit t quadrillion 
    $num = number_format($num, 2, ".", ",");
    $num_arr = explode(".", $num);
    $wholenum = $num_arr[0];
    $decnum = $num_arr[1];
    $whole_arr = array_reverse(explode(",", $wholenum));
    krsort($whole_arr);
    $rettxt = "";
    foreach ($whole_arr as $key => $i) {
        if ($i < 20) {
            $rettxt .= $ones[$i];
        }
        elseif ($i < 100) {
            $rettxt .= $tens[substr($i, 0, 1)];
            $rettxt .= " " . $ones[substr($i, 1, 1)];
        }
        else {
            $rettxt .= $ones[substr($i, 0, 1)] . " " . $hundreds[0];
            $rettxt .= " " . $tens[substr($i, 1, 1)];
            $rettxt .= " " . $ones[substr($i, 2, 1)];
        }
        if ($key > 0) {
            $rettxt .= " " . $hundreds[$key] . " ";
        }
    }
    if ($decnum > 0) {
        $rettxt .= " and ";
        if ($decnum < 20) {
            $rettxt .= $ones[$decnum];
        }
        elseif ($decnum < 100) {
            $rettxt .= $tens[substr($decnum, 0, 1)];
            $rettxt .= " " . $ones[substr($decnum, 1, 1)];
        }
    }
    return $rettxt;
}

if (!function_exists('po_pdf_images_supported')) {
    function po_pdf_images_supported(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatefrompng')
            && function_exists('imagepng');
    }
}

if (!function_exists('po_pdf_is_svg_image_src')) {
    function po_pdf_is_svg_image_src(string $src): bool
    {
        $src = strtolower(trim($src));
        if ($src === '') {
            return false;
        }

        if (str_contains($src, 'image/svg+xml')) {
            return true;
        }

        if (preg_match('/\.svg(\?|#|$)/i', $src)) {
            return true;
        }

        if (is_file($src) && strtolower(pathinfo($src, PATHINFO_EXTENSION)) === 'svg') {
            return true;
        }

        return false;
    }
}

if (!function_exists('po_pdf_safe_dompdf_image_src')) {
    /**
     * Dompdf can render SVG without GD; PNG/JPEG/WebP require the GD extension.
     */
    function po_pdf_safe_dompdf_image_src(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        if (po_pdf_is_svg_image_src($src) || po_pdf_images_supported()) {
            return $src;
        }

        return '';
    }
}

if (!function_exists('po_pdf_logo_url_candidates')) {
    /**
     * @return list<string>
     */
    function po_pdf_logo_url_candidates(): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return array_values(array_filter([
            $baseUrl . '/home/images/logo_tagline.png',
            'https://superadmin.tizzygroup.com/home/images/logo_tagline.png',
            $baseUrl . '/public/home/images/logo_tagline.png',
        ]));
    }
}
if (!function_exists('po_pdf_logo_candidate_paths')) {
    /**
     * @return list<string>
     */
    function po_pdf_logo_candidate_paths(): array
    {
        return [
            public_path('home/images/logo_tagline.png'),
            public_path('public/home/images/logo_tagline.png'),
            public_path('home/images/logo.png'),
            public_path('public/home/images/logo.png'),
        ];
    }
}

if (!function_exists('po_pdf_logo_svg_candidate_paths')) {
    /**
     * @return list<string>
     */
    function po_pdf_logo_svg_candidate_paths(): array
    {
        return [
            public_path('home/images/logo_tagline.svg'),
            public_path('public/home/images/logo_tagline.svg'),
            public_path('home/images/logo.svg'),
            public_path('public/home/images/logo.svg'),
        ];
    }
}

if (!function_exists('po_pdf_default_logo_svg')) {
    function po_pdf_default_logo_svg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="52" viewBox="0 0 200 52" role="img" aria-label="Tizzy Group">
  <text x="2" y="30" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="26" font-weight="700" fill="#0B4F8A">TIZZY</text>
  <text x="2" y="46" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="11" font-weight="600" fill="#333333">GROUP</text>
</svg>
SVG;
    }
}

if (!function_exists('po_pdf_default_logo_svg_data_uri')) {
    function po_pdf_default_logo_svg_data_uri(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(po_pdf_default_logo_svg());
    }
}

if (!function_exists('po_pdf_logo_src_from_svg_candidates')) {
    function po_pdf_logo_src_from_svg_candidates(): string
    {
        foreach (po_pdf_logo_svg_candidate_paths() as $path) {
            if (!is_file($path)) {
                continue;
            }

            $uri = po_pdf_file_to_data_uri($path);
            if ($uri !== null) {
                return $uri;
            }
        }

        return po_pdf_default_logo_svg_data_uri();
    }
}

if (!function_exists('po_pdf_file_to_data_uri')) {
    function po_pdf_file_to_data_uri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };
        if ($mime === null) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}

if (!function_exists('po_pdf_public_relative_path')) {
    function po_pdf_public_relative_path(string $absolutePath): ?string
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $publicRoot = str_replace('\\', '/', (string) (realpath(public_path()) ?: public_path()));
        $resolved = str_replace('\\', '/', (string) (realpath($absolutePath) ?: $absolutePath));

        if ($publicRoot !== '' && str_starts_with($resolved, $publicRoot)) {
            return ltrim(substr($resolved, strlen($publicRoot)), '/');
        }

        return null;
    }
}

if (!function_exists('po_pdf_logo_img_src')) {
    function po_pdf_logo_img_src(?string $hint = null): string
    {
        $candidates = po_pdf_logo_candidate_paths();

        $hint = trim((string) $hint);
        if ($hint !== '' && !preg_match('#^https?://#i', $hint) && !preg_match('#^data:#i', $hint)) {
            $normalizedHint = str_replace('\\', '/', $hint);
            if (is_file($normalizedHint)) {
                array_unshift($candidates, $normalizedHint);
            }
        }

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }

            if (po_pdf_images_supported()) {
                $relative = po_pdf_public_relative_path($path);
                if ($relative !== null && $relative !== '') {
                    return $relative;
                }

                $dataUri = po_pdf_file_to_data_uri($path);
                if ($dataUri !== null) {
                    return $dataUri;
                }

                return str_replace('\\', '/', $path);
            }

            break;
        }

        if (po_pdf_images_supported()) {
            if ($hint !== '' && preg_match('#^https?://#i', $hint)) {
                return $hint;
            }

            foreach (po_pdf_logo_url_candidates() as $url) {
                if ($url !== '') {
                    return $url;
                }
            }
        }

        foreach (po_pdf_logo_svg_candidate_paths() as $path) {
            if (is_file($path)) {
                $relative = po_pdf_public_relative_path($path);
                if ($relative !== null && $relative !== '') {
                    return $relative;
                }

                return str_replace('\\', '/', $path);
            }
        }

        return po_pdf_default_logo_svg_data_uri();
    }
}

if (!function_exists('po_pdf_logo_src')) {
    function po_pdf_logo_src(?string $hint = null): string
    {
        return po_pdf_logo_img_src($hint);
    }
}

if (!function_exists('po_pdf_cancelled_watermark_svg')) {
    function po_pdf_cancelled_watermark_svg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="460" height="120" viewBox="0 0 460 120" role="img" aria-label="Cancelled">
  <g transform="translate(230,60) rotate(-35)" opacity="0.24">
    <rect x="-158" y="-28" width="316" height="56" fill="none" stroke="#c53030" stroke-width="2.2"/>
    <rect x="-150" y="-20" width="300" height="40" fill="none" stroke="#c53030" stroke-width="1"/>
    <text x="0" y="10" text-anchor="middle" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="40" font-weight="700" fill="#c53030" letter-spacing="8">CANCELLED</text>
  </g>
</svg>
SVG;
    }
}

if (!function_exists('po_pdf_cancelled_watermark_img_src')) {
    function po_pdf_cancelled_watermark_img_src(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(po_pdf_cancelled_watermark_svg());
    }
}

if (!function_exists('aadhaar_valid_check_candidate_paths')) {
    /**
     * @return list<string>
     */
    function aadhaar_valid_check_candidate_paths(): array
    {
        return [
            public_path('home/images/aadhaar-valid-check-tick.png'),
            public_path('public/home/images/aadhaar-valid-check-tick.png'),
            public_path('home/images/aadhaar-valid-check.png'),
            public_path('public/home/images/aadhaar-valid-check.png'),
            public_path('home/images/aadhaar-valid-check.svg'),
            public_path('public/home/images/aadhaar-valid-check.svg'),
        ];
    }
}

if (!function_exists('aadhaar_valid_check_img_path')) {
    function aadhaar_valid_check_img_path(): ?string
    {
        foreach (aadhaar_valid_check_candidate_paths() as $path) {
            if (is_file($path)) {
                return str_replace('\\', '/', $path);
            }
        }

        return null;
    }
}

if (!function_exists('aadhaar_signature_stamp_header_png_path')) {
    function aadhaar_signature_stamp_header_png_path(): ?string
    {
        foreach ([
            public_path('home/images/aadhaar-signature-valid-header.png'),
            public_path('public/home/images/aadhaar-signature-valid-header.png'),
        ] as $path) {
            if (is_file($path)) {
                return str_replace('\\', '/', $path);
            }
        }

        return null;
    }
}

if (!function_exists('aadhaar_signature_stamp_header_png_data_uri')) {
    function aadhaar_signature_stamp_header_png_data_uri(): ?string
    {
        $path = aadhaar_signature_stamp_header_png_path();
        if ($path === null) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }
}

if (!function_exists('aadhaar_signature_stamp_header_svg')) {
    function aadhaar_signature_stamp_header_svg(): string
    {
        foreach ([
            public_path('home/images/aadhaar-signature-valid-header.svg'),
            public_path('public/home/images/aadhaar-signature-valid-header.svg'),
        ] as $path) {
            if (is_file($path)) {
                return trim((string) file_get_contents($path));
            }
        }

        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="168" height="76" viewBox="0 0 168 76" role="img" aria-label="Signature valid">
  <defs>
    <filter id="uidTickShadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="1.4" dy="1.6" stdDeviation="0.25" flood-color="#1a1a1a" flood-opacity="0.45"/>
    </filter>
  </defs>
  <text x="84" y="15" text-anchor="middle" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="14.5" font-weight="700" fill="#000000">Signature valid</text>
  <path d="M36 40 L56 60 L132 22" fill="none" stroke="#1a1a1a" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" opacity="0.18" transform="translate(1.4,1.6)"/>
  <path d="M36 40 L56 60 L132 22" fill="none" stroke="#3ecf3e" stroke-width="9" stroke-linecap="round" stroke-linejoin="round" filter="url(#uidTickShadow)"/>
  <path d="M36 40 L56 60 L132 22" fill="none" stroke="#2a8f2a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;
    }
}

if (!function_exists('aadhaar_signature_stamp_header_data_uri')) {
    function aadhaar_signature_stamp_header_data_uri(): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(aadhaar_signature_stamp_header_svg());
    }
}

if (!function_exists('aadhaar_signature_stamp_tick_png_data_uri')) {
    /**
     * Green checkmark as PNG — dompdf renders raster images reliably (SVG img often blank).
     */
    function aadhaar_signature_stamp_tick_png_data_uri(): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = 132;
        $height = 58;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);

        $shadow = imagecolorallocatealpha($image, 20, 20, 20, 70);
        $green = imagecolorallocate($image, 62, 207, 62);
        $edge = imagecolorallocate($image, 42, 125, 42);

        imagesetthickness($image, 9);
        imageline($image, 23, 31, 39, 47, $shadow);
        imageline($image, 39, 47, 117, 15, $shadow);
        imageline($image, 22, 30, 38, 46, $green);
        imageline($image, 38, 46, 116, 14, $green);
        imagesetthickness($image, 2);
        imageline($image, 22, 30, 38, 46, $edge);
        imageline($image, 38, 46, 116, 14, $edge);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (!is_string($png) || $png === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }
}

if (!function_exists('aadhaar_signature_stamp_tick_img_src')) {
    function aadhaar_signature_stamp_tick_img_src(): string
    {
        $png = aadhaar_signature_stamp_tick_png_data_uri();
        if ($png !== null && $png !== '') {
            return $png;
        }

        $svg = trim(aadhaar_signature_stamp_header_svg());

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}

if (!function_exists('aadhaar_signature_stamp_uid_suffix')) {
    function aadhaar_signature_stamp_uid_suffix(string $aadhaarMasked = ''): string
    {
        $masked = trim($aadhaarMasked);
        if ($masked !== '' && preg_match('/(\d{2})$/', $masked, $matches)) {
            return $matches[1];
        }

        return '05';
    }
}

if (!function_exists('aadhaar_signature_stamp_badge_svg_escape')) {
    function aadhaar_signature_stamp_badge_svg_escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('aadhaar_signature_stamp_badge_svg')) {
    /**
     * Official-style Aadhaar eSign stamp as a single SVG (dompdf-safe, no filters/unicode glyphs).
     */
    function aadhaar_signature_stamp_badge_svg(string $stampDate, string $aadhaarMasked = ''): string
    {
        $masked = trim($aadhaarMasked);
        $dateValue = trim($stampDate);
        if ($dateValue === '') {
            $dateValue = now()->format('Y.m.d H:i:s');
        }

        $uidSuffix = '05';
        if ($masked !== '' && preg_match('/(\d{2})$/', $masked, $m)) {
            $uidSuffix = $m[1];
        }

        $line1 = aadhaar_signature_stamp_badge_svg_escape('Digitally signed by DS');
        $line2 = aadhaar_signature_stamp_badge_svg_escape('UNIQUE IDENTIFICATION');
        $line3 = aadhaar_signature_stamp_badge_svg_escape('AUTHORITY OF INDIA ' . $uidSuffix);
        $line4 = $masked !== ''
            ? aadhaar_signature_stamp_badge_svg_escape($masked)
            : '';
        $line5 = aadhaar_signature_stamp_badge_svg_escape('Date: ' . $dateValue);
        $line6 = aadhaar_signature_stamp_badge_svg_escape('GMT+05:30');

        $maskedLine = $line4 !== ''
            ? "\n  <text x=\"6\" y=\"58\" font-family=\"Helvetica, Arial, DejaVu Sans, sans-serif\" font-size=\"6.3\" fill=\"#111111\">{$line4}</text>"
            : '';
        $dateY = $line4 !== '' ? 70 : 58;
        $tzY = $line4 !== '' ? 80 : 68;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="150" height="118" viewBox="0 0 150 118" role="img" aria-label="Aadhaar signature valid">
  <rect x="0.5" y="0.5" width="149" height="117" fill="#ffffff" stroke="#c41e1e" stroke-width="1"/>
  <text x="75" y="13" text-anchor="middle" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="11.5" font-weight="700" fill="#111111">Signature valid</text>
  <text x="6" y="28" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="6.3" fill="#111111">{$line1}</text>
  <text x="6" y="38" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="6.3" fill="#111111">{$line2}</text>
  <text x="6" y="48" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="6.3" fill="#111111">{$line3}</text>{$maskedLine}
  <text x="6" y="{$dateY}" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="6.3" fill="#111111">{$line5}</text>
  <text x="6" y="{$tzY}" font-family="Helvetica, Arial, DejaVu Sans, sans-serif" font-size="6.3" fill="#111111">{$line6}</text>
  <path d="M24 34 L40 50 L126 16" fill="none" stroke="#1a1a1a" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.18" transform="translate(1.3,1.5)"/>
  <path d="M24 34 L40 50 L126 16" fill="none" stroke="#3ecf3e" stroke-width="8.5" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M24 34 L40 50 L126 16" fill="none" stroke="#2a7d2a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;
    }
}

if (!function_exists('aadhaar_signature_stamp_badge_img_src')) {
    function aadhaar_signature_stamp_badge_img_src(string $stampDate, string $aadhaarMasked = ''): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(aadhaar_signature_stamp_badge_svg($stampDate, $aadhaarMasked));
    }
}

if (!function_exists('aadhaar_signature_stamp_header_img_src')) {
    function aadhaar_signature_stamp_header_img_src(): string
    {
        return aadhaar_signature_stamp_header_data_uri();
    }
}

if (!function_exists('aadhaar_valid_check_default_svg')) {
    function aadhaar_valid_check_default_svg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110" viewBox="0 0 110 110" role="img" aria-label="Verified">
  <circle cx="55" cy="55" r="48" fill="none" stroke="#1b7f3a" stroke-width="4.5" opacity="0.28"/>
  <circle cx="55" cy="55" r="41" fill="none" stroke="#2e9e4c" stroke-width="3" opacity="0.5"/>
  <path d="M31 56 L47 72 L79 38" fill="none" stroke="#1b7f3a" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;
    }
}

if (!function_exists('aadhaar_valid_check_img_src')) {
    function aadhaar_valid_check_img_src(): string
    {
        foreach (aadhaar_valid_check_candidate_paths() as $path) {
            if (!is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        }

        return 'data:image/svg+xml;base64,' . base64_encode(aadhaar_valid_check_default_svg());
    }
}

if (!function_exists('format_display_name')) {
    function format_display_name(?string $value, string $fallback = '—'): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if ($value === '') {
            return $fallback;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}

if (!function_exists('partner_discount_type_label')) {
    function partner_discount_type_label(?string $type): ?string
    {
        return \App\Support\PartnerDiscountType::label($type);
    }
}

if (!function_exists('partner_discount_type_options')) {
    /** @return array<string, string> */
    function partner_discount_type_options(): array
    {
        return \App\Support\PartnerDiscountType::options();
    }
}

}
