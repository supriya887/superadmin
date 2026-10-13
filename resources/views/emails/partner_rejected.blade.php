<h1>Partner Account Rejected</h1>

<p>Dear {{ $partner->name }},</p>

<p>We regret to inform you that your partner account application with {{ config('app.name') }} has been rejected.</p>

@if($rejectionReason !== '')
    <p><strong>Reason:</strong> {{ $rejectionReason }}</p>
@endif

<p>If you have any questions or would like to re-apply, please contact our support team.</p>

<p>Regards,</p>
<p>The {{ config('app.name') }} Team</p>