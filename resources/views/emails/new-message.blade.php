{{-- resources/views/emails/new-message.blade.php --}}
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
    .header { background: linear-gradient(135deg, #ec4899, #a855f7); padding: 32px; text-align: center; }
    .header h1 { color: white; margin: 0; font-size: 24px; }
    .body { padding: 32px; }
    .message-box { background: #f9fafb; border-left: 4px solid #ec4899; border-radius: 8px; padding: 16px; margin: 20px 0; }
    .message-box p { margin: 0; color: #374151; font-size: 15px; line-height: 1.6; }
    .btn { display: inline-block; margin-top: 24px; padding: 14px 32px; background: linear-gradient(135deg, #ec4899, #a855f7); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
    .footer { text-align: center; padding: 20px; color: #9ca3af; font-size: 12px; background: #f9fafb; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>💬 New Message</h1>
    </div>
    <div class="body">
      <p>Hi <strong>{{ $receiverName }}</strong>,</p>
      <p>You have a new message from <strong>{{ $senderName }}</strong>:</p>

      <div class="message-box">
        <p>{{ Str::limit($chatMessage->message, 200) }}</p>
      </div>

      <p>Log in to reply:</p>
      <a href="{{ config('app.url') }}/messages" class="btn">View Message →</a>
    </div>
    <div class="footer">
      <p>You're receiving this because you have an account on WeWorkPerHour.</p>
      <p>© {{ date('Y') }} WeWorkPerHour. All rights reserved.</p>
    </div>
  </div>
</body>
</html>