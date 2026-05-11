<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .header { background: linear-gradient(135deg, #EE009D, #ff4db8); padding: 40px 30px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 28px; }
    .header p { color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 15px; }
    .body { padding: 40px 30px; }
    .body p { color: #444; font-size: 15px; line-height: 1.7; }
    .token-box { background: #f5f1f1; border: 2px dashed #EE009D; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
    .token-box p { margin: 0 0 8px; color: #666; font-size: 13px; }
    .token-box span { font-size: 22px; font-weight: bold; color: #EE009D; letter-spacing: 3px; }
    .footer { background: #f5f1f1; padding: 20px 30px; text-align: center; }
    .footer p { color: #999; font-size: 12px; margin: 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🎉 Welcome to Workason!</h1>
      <p>You're now part of our newsletter community</p>
    </div>
    <div class="body">
      <p>Hi <strong>{{ $subscriberEmail }}</strong>,</p>
      <p>Thank you for subscribing to the <strong>Workason Newsletter</strong>! You'll be the first to receive:</p>
      <ul style="color:#444; font-size:15px; line-height:2;">
        <li>🚀 Latest job vacancies</li>
        <li>💡 Career tips & insights</li>
        <li>📢 Platform updates & announcements</li>
      </ul>
      <p>Here is your unique subscriber token — keep it safe:</p>
      <div class="token-box">
        <p>Your Subscriber Token</p>
        <span>{{ $token }}</span>
      </div>
      <p>You can use this token to manage your subscription preferences at any time.</p>
      <p>Welcome aboard! 🙌</p>
      <p style="color:#EE009D; font-weight:bold;">— The Workason Team</p>
    </div>
    <div class="footer">
      <p>© {{ date('Y') }} Workason. All rights reserved.</p>
      <p>You're receiving this because you subscribed at workason.com</p>
    </div>
  </div>
</body>
</html>