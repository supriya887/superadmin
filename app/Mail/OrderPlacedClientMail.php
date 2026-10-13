<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderPlacedClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $order;
    public array $attachmentsMeta;
    public string $logoFilename;
    public string $logoUrl;
    public string $logoPath;
    public string $providerAlt;

    private const DEFAULT_PROVIDER_LOGO = 'providers/logos/idn7VlSxIb1DDHeVfcTbekBNJGTVu0DJkufL0ARk.png';
    private const LOGO_BASE_URL = 'https://superadmin.tizzygroup.com/superadmin/provider-logo';

    public function __construct(object $order, array $attachmentsMeta = [])
    {
        $this->order = $order;
        $this->attachmentsMeta = $attachmentsMeta;

        $logoRel = $this->resolveProviderLogoRelativePath($order);
        $this->logoFilename = basename(ltrim($logoRel, '/'));
        $this->logoUrl = self::LOGO_BASE_URL . '/' . $this->logoFilename;
        $this->logoPath = $this->resolveLogoAbsolutePath($this->logoFilename);
        $this->providerAlt = $this->resolveProviderName($order);
    }

    public function build()
    {
        $mail = $this->subject('Your order is completed')
            ->view('emails.order_placed_client');

        foreach ($this->attachmentsMeta as $item) {
            $type = (string) ($item['type'] ?? '');
            if ($type === 'data' && isset($item['data'], $item['name'])) {
                $mail->attachData(
                    $item['data'],
                    (string) $item['name'],
                    ['mime' => (string) ($item['mime'] ?? 'application/octet-stream')]
                );
            } elseif ($type === 'path' && !empty($item['path']) && is_file((string) $item['path'])) {
                $mail->attach(
                    (string) $item['path'],
                    [
                        'as' => (string) ($item['name'] ?? basename((string) $item['path'])),
                        'mime' => (string) ($item['mime'] ?? 'application/octet-stream'),
                    ]
                );
            }
        }

        return $mail;
    }

    private function resolveProviderLogoRelativePath(object $order): string
    {
        $planId = (int) ($order->plan_id ?? 0);
        if ($planId > 0) {
            $plan = Plan::query()->whereKey($planId)->first();
            if ($plan && (int) ($plan->provider_id ?? 0) > 0) {
                $provider = Provider::query()->whereKey((int) $plan->provider_id)->first();
                $logo = trim((string) ($provider->logo ?? ''));
                if ($logo !== '') {
                    if (str_contains($logo, '://')) {
                        $path = (string) (parse_url($logo, PHP_URL_PATH) ?? '');
                        $filename = basename($path);

                        return $filename !== '' ? 'providers/logos/' . $filename : self::DEFAULT_PROVIDER_LOGO;
                    }

                    return $logo;
                }
            }
        }

        return self::DEFAULT_PROVIDER_LOGO;
    }

    private function resolveProviderName(object $order): string
    {
        $planId = (int) ($order->plan_id ?? 0);
        if ($planId > 0) {
            $plan = Plan::query()->whereKey($planId)->first();
            if ($plan && (int) ($plan->provider_id ?? 0) > 0) {
                $provider = Provider::query()->whereKey((int) $plan->provider_id)->first();
                $name = trim((string) ($provider->name ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return 'Tizzy Mail';
    }

    private function resolveLogoAbsolutePath(string $filename): string
    {
        $candidate = storage_path('app/public/providers/logos/' . $filename);
        if (is_file($candidate)) {
            return $candidate;
        }

        $fallback = storage_path('app/public/' . self::DEFAULT_PROVIDER_LOGO);

        return is_file($fallback) ? $fallback : '';
    }
}
