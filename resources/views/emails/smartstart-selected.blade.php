<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .badge { display: inline-block; background: #16a34a; color: #fff; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: bold; }
        .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .card h3 { margin: 0 0 12px; font-size: 16px; color: #111; }
        .detail { margin: 6px 0; font-size: 14px; }
        .label { font-weight: bold; color: #555; }
        .cta { display: inline-block; margin-top: 24px; background: #7c3aed; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">

    <p>Hi <strong>{{ $freelancer->name }}</strong>,</p>

    <p>Great news! <strong>{{ $employer->name }}</strong> has reviewed your profile and selected you from their curated Talent Pack for the following project:</p>

    <div class="card">
        <h3>{{ $smartStart->title }}</h3>

        <p class="detail"><span class="label">Project Type:</span> {{ $smartStart->project_type }}</p>

        @if($smartStart->description)
        <p class="detail"><span class="label">Description:</span><br>{{ $smartStart->description }}</p>
        @endif

        @if($smartStart->budget_min || $smartStart->budget_max)
        <p class="detail">
            <span class="label">Budget:</span>
            ₦{{ number_format($smartStart->budget_min) }} – ₦{{ number_format($smartStart->budget_max) }}
        </p>
        @endif

        @if($smartStart->deadline)
        <p class="detail"><span class="label">Deadline:</span> {{ \Carbon\Carbon::parse($smartStart->deadline)->format('d M Y') }}</p>
        @endif

        @if($smartStart->urgency)
        <p class="detail"><span class="label">Urgency:</span> {{ ucfirst($smartStart->urgency) }}</p>
        @endif

        @if($smartStart->extra_notes)
        <p class="detail"><span class="label">Additional Notes:</span><br>{{ $smartStart->extra_notes }}</p>
        @endif
    </div>

    <p><strong>What happens next?</strong></p>
    <ol>
        <li>Log into your Workason dashboard</li>
        <li>Go to <strong>Messages</strong> to connect with {{ $employer->name }}</li>
        <li>Send your proposal including your timeline and rate</li>
        <li>Once the employer accepts, the project will move to escrow and work begins</li>
    </ol>

    <p>This is a SmartStart™ project — the employer has already paid a matching fee and is ready to move forward quickly. We encourage you to respond within <strong>24 hours</strong>.</p>

    <div class="footer">
        <p>You received this email because your profile was selected as part of a Workason SmartStart™ Talent Pack.<br>
        &copy; {{ date('Y') }} Workason. All rights reserved.</p>
    </div>

</div>
</body>
</html>
