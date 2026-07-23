<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #4c1d95; padding: 28px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 26px; letter-spacing: 3px; margin: 0; }
        .header p { color: #ddd6fe; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #7c3aed; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 18px; color: #4c1d95; font-weight: bold; margin-bottom: 14px; }
        .alert-box { background: #faf5ff; border-left: 4px solid #7c3aed; padding: 16px 20px; margin: 20px 0; border-radius: 0 6px 6px 0; }
        .alert-box table { width: 100%; border-collapse: collapse; }
        .alert-box td { padding: 6px 0; font-size: 13.5px; }
        .alert-box td:first-child { color: #4c1d95; font-weight: bold; width: 45%; }
        .footer { background: #f9f9f9; border-top: 3px solid #7c3aed; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #1b5e20; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>PERSONAL TRAINER PACKAGE — EXPIRED</p>
        </div>
        <div class="bar"></div>
        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>
            <p>
                Your <strong>Personal Trainer Package</strong> at MTTM GYM has <strong>expired</strong>.
                Renew now to continue your personalized training sessions and keep progressing toward your goals.
            </p>
            <div class="alert-box">
                <table>
                    <tr>
                        <td>Member Name</td>
                        <td><strong>{{ $memberName }}</strong></td>
                    </tr>
                    <tr>
                        <td>PT Package</td>
                        <td><strong>{{ $packageName }}</strong></td>
                    </tr>
                    <tr>
                        <td>Expired On</td>
                        <td><strong>{{ $expiryDate }}</strong></td>
                    </tr>
                </table>
            </div>
            <p>
                Contact the gym reception to renew your PT package and get back on track with your trainer.
            </p>
            <p>
                Don't stop now!<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>
        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply.
        </div>
    </div>
</body>
</html>
