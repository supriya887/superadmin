<?php

namespace App\Support;

final class PoPdfAssets
{
    public static function pdfImagesSupported(): bool
    {
        return po_pdf_images_supported();
    }

    public static function aadhaarValidCheckImgPath(): ?string
    {
        return aadhaar_valid_check_img_path();
    }

    public static function aadhaarValidCheckImgSrc(): string
    {
        return aadhaar_valid_check_img_src();
    }
}
