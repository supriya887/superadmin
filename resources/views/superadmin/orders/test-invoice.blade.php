<!DOCTYPE html>
<html>
<head>
    <title>E-Invoice Debug Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #155724; background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107; }
        .info { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #2196f3; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; overflow-x: auto; border-radius: 5px; font-size: 12px; }
        .einvoice-box { border: 2px solid #1e58b4; padding: 20px; margin: 20px 0; border-radius: 8px; background: #f0f7ff; }
        .qr-code { text-align: center; margin: 15px; }
        .qr-code img { max-width: 150px; border: 1px solid #ddd; padding: 5px; background: white; }
        h1 { color: #1e58b4; margin-top: 0; }
        h3 { color: #333; margin: 10px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-live { background: #28a745; color: white; }
        .badge-test { background: #ffc107; color: #333; }
        hr { margin: 20px 0; border: none; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 E-Invoice Debug Test</h1>
        <p>Order ID: <strong>{{ $order->id ?? 'N/A' }}</strong></p>
        <p>Invoice Number: <strong>{{ $invoice_number ?? 'N/A' }}</strong></p>
        <p>Mode: <span class="badge {{ config('services.einvoice.mode') === 'live' ? 'badge-live' : 'badge-test' }}">{{ config('services.einvoice.mode', 'test') }}</span></p>
        
        <hr>
        
        @php
            $einvoice = $einvoice ?? [];
            $hasEinvoice = !empty($einvoice['irn']);
        @endphp
        
        @if($hasEinvoice)
            <div class="success">
                ✅ <strong>E-INVOICE FOUND!</strong><br>
                IRN: {{ $einvoice['irn'] }}
            </div>
            
            <div class="einvoice-box">
                <h3>📄 E-Invoice Details:</h3>
                <p><strong>IRN (Full):</strong> <code style="font-size: 10px; word-break: break-all;">{{ $einvoice['irn'] }}</code></p>
                <p><strong>Acknowledgement No:</strong> {{ $einvoice['ack_no'] ?: 'N/A' }}</p>
                <p><strong>Acknowledgement Date:</strong> {{ $einvoice['ack_date'] ?: 'N/A' }}</p>
                <p><strong>Status:</strong> 
                    @if($einvoice['is_cancelled'] ?? false)
                        <span style="color: red;">CANCELLED</span>
                    @else
                        <span style="color: green;">ACTIVE</span>
                    @endif
                </p>
                <p><strong>E-Invoice PDF URL:</strong> 
                    @if(!empty($einvoice['e_invoice_pdf_url']))
                        <a href="{{ $einvoice['e_invoice_pdf_url'] }}" target="_blank">Download PDF</a>
                    @else
                        Not available
                    @endif
                </p>
                
                @if(!empty($einvoice['qr_src']))
                    <div class="qr-code">
                        <h4>QR Code (Scan to verify on GST portal):</h4>
                        <img src="{{ $einvoice['qr_src'] }}" alt="E-Invoice QR Code">
                        <p><small>The QR code contains digitally signed invoice data</small></p>
                    </div>
                @else
                    <div class="warning">⚠️ QR Code not available - signed QR data missing</div>
                @endif
            </div>
        @else
            <div class="error">
                ❌ <strong>NO E-INVOICE FOUND</strong> for this order.
            </div>
            
            @if(isset($einvoice_error))
                <div class="error">
                    <strong>Error during generation:</strong><br>
                    {{ $einvoice_error }}
                </div>
            @endif
            
            <div class="info">
                <h4>💡 What to do:</h4>
                <ol>
                    <li>Check if e-invoicing is enabled in .env (<code>EINVOICE_ENABLED=true</code>)</li>
                    <li>Check if credentials are correct (<code>EINVOICE_MODE=live</code> or <code>test</code>)</li>
                    <li>Run: <code>php artisan config:clear</code> to refresh config</li>
                    <li>Check logs: <code>tail -f storage/logs/laravel.log</code></li>
                </ol>
            </div>
            
            <form method="POST" action="{{ route('superadmin.orders.einvoice.cancel', $order->id) }}" style="margin-top: 15px;">
                @csrf
                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    Generate E-Invoice Now
                </button>
            </form>
        @endif
        
        <hr>
        
        <div class="info">
            <h4>📊 Debug Information:</h4>
            <p><strong>Order ID:</strong> {{ $order->id ?? 'N/A' }}</p>
            <p><strong>Order No:</strong> {{ $order->order_no ?? 'N/A' }}</p>
            <p><strong>Customer:</strong> {{ $customer['name'] ?? 'N/A' }}</p>
            <p><strong>Amount:</strong> ₹{{ number_format($total ?? 0, 2) }}</p>
            <p><strong>GST Total:</strong> ₹{{ number_format($gst_total ?? 0, 2) }}</p>
            <p><strong>Created At:</strong> {{ $order->created_at ?? 'N/A' }}</p>
        </div>
        
        <div class="info">
            <h4>🔧 Environment Configuration:</h4>
            <p><strong>E-Invoice Enabled:</strong> {{ config('services.einvoice.enabled', false) ? '✅ YES' : '❌ NO' }}</p>
            <p><strong>E-Invoice Mode:</strong> {{ config('services.einvoice.mode', 'test') }}</p>
            <p><strong>Base URL:</strong> {{ config('services.einvoice.mode') === 'live' ? config('services.einvoice.live.base_url', 'not set') : config('services.einvoice.test.base_url', 'not set') }}</p>
            <p><strong>Auto Generate:</strong> {{ config('services.einvoice.auto_generate', true) ? '✅ YES' : '❌ NO' }}</p>
        </div>
        
        <div class="info">
            <h4>📦 Raw E-Invoice Data:</h4>
            <pre>{{ json_encode($einvoice ?? [], JSON_PRETTY_PRINT) }}</pre>
        </div>
        
        <div class="info">
            <h4>📋 All Available Data Keys:</h4>
            <pre>{{ json_encode(array_keys(get_defined_vars()['__data'] ?? []), JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
</body>
</html>