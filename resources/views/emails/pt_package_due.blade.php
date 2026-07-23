<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1e3a5f; padding: 28px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 26px; letter-spacing: 3px; margin: 0; }
        .header p { color: #bfdbfe; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #3b82f6; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 18px; color: #1e3a5f; font-weight: bold; margin-bottom: 14px; }
        .info-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px 20px; margin: 20px 0; border-radius: 0 6px 6px 0; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 6px 0; font-size: 13.5px; }
        .info-box td:first-child { color: #1e3a5f; font-weight: bold; width: 45%; }
        .badge { display: inline-block; background: #1e3a5f; color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; }
        .footer { background: #f9f9f9; border-top: 3px solid #3b82f6; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #1b5e20; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>PERSONAL TRAINER PACKAGE — EXPIRY REMINDER</p>
        </div>
        <div class="bar"></div>
        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>
            <p>
                Your <strong>Personal Trainer Package</strong> is expiring soon.
                Renew it to keep training with your personal trainer and stay on track with your fitness goals.
            </p>
            <div class="info-box">
                <table>
                    <tr>
                        <td>Member Name</td>
                        <td><strong>{{ $memberName }}</strong></td>
                    </tr>
                    <tr>
                        <td>PT Package</td>
                        <td><span class="badge">{{ $packageName }}</span></td>
                    </tr>
                    <tr>
                        <td>Expiry Date</td>
                        <td><strong>{{ $expiryDate }}</strong></td>
                    </tr>
                </table>
            </div>
            <p>
                Contact the gym reception to renew your PT package and continue your personalized training sessions.
            </p>
            <p>
                Keep pushing!<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>
        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply.
        </div>
    </div>
</body>
</html>
