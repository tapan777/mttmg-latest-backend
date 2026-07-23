<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #b45309; padding: 28px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 26px; letter-spacing: 3px; margin: 0; }
        .header p { color: #fde68a; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #f59e0b; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 18px; color: #b45309; font-weight: bold; margin-bottom: 14px; }
        .alert-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; margin: 20px 0; border-radius: 0 6px 6px 0; }
        .alert-box table { width: 100%; border-collapse: collapse; }
        .alert-box td { padding: 6px 0; font-size: 13.5px; }
        .alert-box td:first-child { color: #b45309; font-weight: bold; width: 45%; }
        .amount { font-size: 22px; font-weight: bold; color: #dc2626; }
        .footer { background: #f9f9f9; border-top: 3px solid #f59e0b; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #1b5e20; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>PAYMENT DUE REMINDER</p>
        </div>
        <div class="bar"></div>
        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>
            <p>
                We noticed that you have an <strong>outstanding balance</strong> on your membership account.
                Kindly clear your dues at the earliest to continue enjoying uninterrupted gym access.
            </p>
            <div class="alert-box">
                <table>
                    <tr>
                        <td>Member Name</td>
                        <td><strong>{{ $memberName }}</strong></td>
                    </tr>
                    <tr>
                        <td>Package</td>
                        <td><strong>{{ $packageName }}</strong></td>
                    </tr>
                    <tr>
                        <td>Due Amount</td>
                        <td><span class="amount">&#8377; {{ $dueAmount }}</span></td>
                    </tr>
                    <tr>
                        <td>Due Date</td>
                        <td><strong>{{ $expiryDate }}</strong></td>
                    </tr>
                </table>
            </div>
            <p>
                Please visit the gym reception or contact us to make your payment.
                Ignoring this may result in suspension of your gym access.
            </p>
            <p>
                Thank you,<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>
        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply.
        </div>
    </div>
</body>
</html>
