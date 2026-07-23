<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #222;
            padding: 40px;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1a3c6e;
            padding-bottom: 14px;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 22px;
            color: #1a3c6e;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header p {
            font-size: 11px;
            color: #555;
            margin-top: 3px;
        }
        .ref-date {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
            color: #555;
        }
        .subject {
            font-weight: bold;
            font-size: 14px;
            text-decoration: underline;
            margin-bottom: 16px;
        }
        .salutation { margin-bottom: 14px; }
        .body-text { line-height: 1.7; margin-bottom: 14px; }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table.details th, table.details td {
            border: 1px solid #ccc;
            padding: 8px 12px;
            font-size: 12px;
        }
        table.details th {
            background: #1a3c6e;
            color: #fff;
            text-align: left;
            width: 40%;
        }
        .note {
            background: #f5f8ff;
            border-left: 4px solid #1a3c6e;
            padding: 10px 14px;
            font-size: 12px;
            margin: 16px 0;
        }
        .closing { margin-top: 30px; line-height: 1.8; }
        .sign-area { margin-top: 50px; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>MAA TARA TARINI MULTI GYM</h1>
        <p>MTTMGYM &nbsp;|&nbsp; Official Offer Letter</p>
    </div>

    <div class="ref-date">
        <span>Ref No: MTTMG/OL/{{ $employee->id }}/{{ date('Y') }}</span>
        <span>Date: {{ date('d-m-Y') }}</span>
    </div>

    <div class="subject">SUBJECT: OFFER OF EMPLOYMENT</div>

    <div class="salutation">Dear <strong>{{ $employee->name }}</strong>,</div>

    <div class="body-text">
        We are pleased to inform you that after reviewing your application and interview performance,
        you have been selected for the position of <strong>{{ $employee->designation }}</strong>
        at <strong>MAA TARA TARINI MULTI GYM (MTTMGYM)</strong>.
    </div>

    <div class="body-text">
        Please find below the details of your appointment:
    </div>

    <table class="details">
        <tr>
            <th>Employee Name</th>
            <td>{{ $employee->name }}</td>
        </tr>
        <tr>
            <th>Designation</th>
            <td>{{ $employee->designation }}</td>
        </tr>
        <tr>
            <th>Date of Joining</th>
            <td>{{ date('d-m-Y', strtotime($employee->joining_date)) }}</td>
        </tr>
        <tr>
            <th>CTC / Monthly Salary</th>
            <td>₹ {{ number_format($employee->salary, 2) }}</td>
        </tr>
        <tr>
            <th>Blood Group</th>
            <td>{{ $employee->blood_group }}</td>
        </tr>
        <tr>
            <th>Contact</th>
            <td>{{ $employee->phone }}</td>
        </tr>
        <tr>
            <th>Address</th>
            <td>{{ $employee->address }}</td>
        </tr>
    </table>

    <div class="note">
        Kindly confirm your acceptance of this offer by reporting on the date of joining mentioned above.
        Please carry your original documents and two passport-size photographs at the time of joining.
    </div>

    <div class="body-text">
        We look forward to having you on our team and wish you a successful and rewarding journey with us. 😊
    </div>

    <div class="closing">
        Warm Regards,<br>
        <strong>HR Department</strong><br>
        MAA TARA TARINI MULTI GYM (MTTMGYM)
    </div>

    <div class="sign-area">
        <table width="100%">
            <tr>
                <td width="50%">
                    ___________________________<br>
                    <small>Authorised Signatory</small>
                </td>
                <td width="50%" style="text-align:right;">
                    ___________________________<br>
                    <small>Employee Signature &amp; Date</small>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This is a computer-generated offer letter. &nbsp;|&nbsp; MTTMGYM &copy; {{ date('Y') }}
    </div>

</body>
</html>
