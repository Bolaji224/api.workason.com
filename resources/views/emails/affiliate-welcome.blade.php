<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .hero { background: #7c3aed; color: #fff; border-radius: 8px; padding: 28px 24px; text-align: center; }
        .hero h1 { margin: 0 0 8px; font-size: 22px; }
        .hero p { margin: 0; opacity: .9; font-size: 15px; }
        .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin: 24px 0; }
        .card h3 { margin: 0 0 12px; font-size: 16px; color: #111; }
        .code-box { background: #ede9fe; border: 2px dashed #7c3aed; border-radius: 6px; padding: 14px 20px; text-align: center; margin: 16px 0; }
        .code-box .code { font-size: 22px; font-weight: bold; color: #5b21b6; letter-spacing: 2px; }
        .link-box { background: #f3f4f6; border-radius: 6px; padding: 10px 14px; word-break: break-all; font-size: 13px; color: #4b5563; margin-top: 8px; }
        .cta { display: inline-block; margin-top: 24px; background: #7c3aed; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: bold; }
        .steps { counter-reset: step; padding: 0; list-style: none; }
        .steps li { counter-increment: step; margin: 10px 0; padding-left: 36px; position: relative; }
        .steps li::before { content: counter(step); position: absolute; left: 0; top: 0; background: #7c3aed; color: #fff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="container">

    <div class="hero">
        <h1>You're now a Workason Affiliate! 🎉</h1>
        <p>Start sharing your link and earn commission on every referred transaction.</p>
    </div>

    <p>Hi <strong>{{ $user->name ?? ($user->first_name . ' ' . $user->last_name) }}</strong>,</p>

    <p>Your Workason Affiliate account is now <strong>active</strong>. Here are your unique details:</p>

    <div class="card">
        <h3>Your Affiliate ID</h3>
        <div class="code-box">
            <div class="code">{{ $affiliate->affiliate_id }}</div>
        </div>

        <h3 style="margin-top:16px">Your Referral Code</h3>
        <div class="code-box">
            <div class="code">{{ $affiliate->referral_code }}</div>
        </div>

        <h3 style="margin-top:16px">Your Referral Link</h3>
        <div class="link-box">{{ $affiliate->referral_link }}</div>
    </div>

    <p><strong>How it works:</strong></p>
    <ol class="steps">
        <li>Share your referral link with employers and freelancers.</li>
        <li>When they click your link and register on Workason, they're attributed to you.</li>
        <li>When their first employer payment is approved, you earn a commission on the platform's earnings.</li>
        <li>Track everything from your Affiliate Dashboard.</li>
        <li>Once your commission is approved and reaches the payout threshold, we'll process your payment.</li>
    </ol>

    <p>Log into your Workason dashboard to track referrals and commissions in real time.</p>

    <div class="footer">
        <p>You received this email because you joined the Workason Affiliate Programme.<br>
        &copy; {{ date('Y') }} Workason. All rights reserved.</p>
    </div>

</div>
</body>
</html>
