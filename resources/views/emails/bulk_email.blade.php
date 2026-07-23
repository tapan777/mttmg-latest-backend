<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1b5e20; padding: 24px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 24px; letter-spacing: 3px; margin: 0; }
        .header p { color: #a5d6a7; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #4caf50; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 16px; color: #1b5e20; font-weight: bold; margin-bottom: 16px; }
        .message-content { white-space: pre-line; }
        .footer { background: #f9f9f9; border-top: 3px solid #4caf50; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>EXCELLENCE IN FITNESS &nbsp;|&nbsp; YOUR HEALTH, OUR PRIORITY</p>
        </div>
        <div class="bar"></div>

        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>
            <div class="message-content">{{ $emailBody }}</div>
            <br>
            <p>
                Regards,<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>

        <div class="footer">
            This email was sent by <span>MTTM GYM</span>. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
