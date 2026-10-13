@php
    $aadhaarMeta = $wizardAadhaarMeta ?? [];
    $aadhaarVerified = !empty($aadhaarMeta['verified']);
@endphp
<div class="border rounded p-3 bg-light">
    <h6 class="mb-2">Aadhaar Verification</h6>
    @if ($aadhaarVerified)
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <input type="text" class="form-control form-control-sm bg-white" value="Verified" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Aadhaar No.</label>
                <input type="text" class="form-control form-control-sm bg-white"
                    value="{{ (string) ($aadhaarMeta['masked'] ?? '-') }}" readonly>
            </div>
        </div>
        <input type="hidden" id="aadhar_verified_local" value="1">
    @else
        <div class="row g-2" id="aadhaar_number_block">
            <div class="col-md-6">
                <label class="form-label">Aadhaar Number</label>
                <input type="text" id="aadhaar_number_local" class="form-control form-control-sm" maxlength="12"
                    placeholder="12 digit Aadhaar" inputmode="numeric" autocomplete="off">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-primary w-100" id="aadhaar_send_otp_btn">Send OTP</button>
            </div>
        </div>
        <div class="row g-2 d-none" id="aadhaar_otp_block">
            <div class="col-md-4">
                <label class="form-label">OTP</label>
                <input type="text" id="aadhaar_otp_local" class="form-control form-control-sm" maxlength="6"
                    placeholder="6 digit OTP" inputmode="numeric" autocomplete="one-time-code">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-primary w-100" id="aadhaar_verify_local_btn">Verify Aadhaar</button>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="aadhaar_back_btn">Change Aadhaar</button>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <a href="javascript:void(0);" class="d-none small" id="aadhaar_resend_otp_btn">Resend OTP</a>
            </div>
        </div>
        <input type="hidden" id="aadhar_verified_local" value="0">
        <p class="small text-muted mb-0 mt-2">Enter your Aadhaar number, send OTP, then verify to place the order.</p>
    @endif
</div>
