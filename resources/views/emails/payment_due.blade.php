<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1b5e20; padding: 28px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 26px; letter-spacing: 3px; margin: 0; }
        .header p { color: #a5d6a7; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #4caf50; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 18px; color: #1b5e20; font-weight: bold; margin-bottom: 14px; }
        .info-box { background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 16px 20px; margin: 20px 0; border-radius: 0 6px 6px 0; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 6px 0; font-size: 13.5px; }
        .info-box td:first-child { color: #2e7d32; font-weight: bold; width: 45%; }
        .badge { display: inline-block; background: #2e7d32; color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; }
        .footer { background: #f9f9f9; border-top: 3px solid #4caf50; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>MEMBERSHIP RENEWAL REMINDER</p>
        </div>
        <div class="bar"></div>
        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>
            <p>
                Your membership is approaching its expiry date. Renew now to continue enjoying
                all the benefits of MTTM GYM without interruption.
            </p>
            <div class="info-box">
                <table>
                    <tr>
                        <td>Member Name</td>
                        <td><strong>{{ $memberName }}</strong></td>
                    </tr>
                    <tr>
                        <td>Package</td>
                        <td><span class="badge">{{ $packageName }}</span></td>
                    </tr>
                    <tr>
                        <td>Expiry Date</td>
                        <td><strong>{{ $expiryDate }}</strong></td>
                    </tr>
                </table>
            </div>
            <p>
                Please visit the gym reception or contact us before your membership expires.
                Early renewal may qualify you for special offers!
            </p>
            <p>
                See you at the gym!<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>
        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply.
        </div>
    </div>
</body>
</html>
