<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - {{ $receipt_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 2px solid #333;
            background: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            border-radius: 50%;
        }
        
        .school-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .school-details {
            font-size: 11px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .receipt-number {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
            border-bottom: 1px dotted #ddd;
            padding-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #2c3e50;
        }
        
        .info-value {
            flex: 1;
            color: #333;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 2px solid #333;
        }
        
        .payment-table th,
        .payment-table td {
            border: 1px solid #333;
            padding: 10px;
            text-align: left;
        }
        
        .payment-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: bold;
        }
        
        .total-row {
            background-color: #e8f4f8;
            font-weight: bold;
        }
        
        .amount-section {
            background-color: #f8f9fa;
            padding: 15px;
            border: 2px solid #333;
            margin: 20px 0;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .amount-row.total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
            color: #e74c3c;
        }
        
        .payment-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 10px;
            color: #666;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0, 0, 0, 0.05);
            z-index: -1;
            pointer-events: none;
        }
        
        @media print {
            .receipt-container {
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 10px;
            }
            
            body {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">PAID</div>
    
    <div class="receipt-container">
        <!-- Header Section -->
        <div class="header">
            @if(!empty($school['logo']))
                <img src="{{ $school['logo'] }}" alt="School Logo" class="school-logo">
            @endif
            <div class="school-name">{{ $school['name'] }}</div>
            <div class="school-details">
                @if(!empty($school['address']))
                    {{ $school['address'] }}<br>
                @endif
                @if(!empty($school['phone']))
                    Phone: {{ $school['phone'] }} | 
                @endif
                @if(!empty($school['email']))
                    Email: {{ $school['email'] }}
                @endif
            </div>
            <div class="receipt-title">Fee Payment Receipt</div>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <div>
                <strong>Receipt No:</strong> <span class="receipt-number">{{ $receipt_number }}</span>
            </div>
            <div>
                <strong>Date:</strong> {{ $payment_date }}
            </div>
        </div>

        <!-- Student Information -->
        <div class="info-section">
            <h3 style="margin-bottom: 10px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px;">Student Information</h3>
            <div class="info-row">
                <span class="info-label">Student Name:</span>
                <span class="info-value">{{ $student['name'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Admission Number:</span>
                <span class="info-value">{{ $student['admission_number'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Class:</span>
                <span class="info-value">{{ $student['class'] }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Father's Name:</span>
                <span class="info-value">{{ $student['father_name'] }}</span>
            </div>
        </div>

        <!-- Fee Details Table -->
        <table class="payment-table">
            <thead>
                <tr>
                    <th>Fee Description</th>
                    <th>Component</th>
                    <th style="text-align: right;">Total Amount</th>
                    <th style="text-align: right;">Paid Amount</th>
                    <th style="text-align: right;">Remaining</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $fee_structure['name'] }}</td>
                    <td>{{ $fee_structure['component_name'] }}</td>
                    <td class="amount-cell">₹{{ number_format($fee_structure['total_amount'], 2) }}</td>
                    <td class="amount-cell">₹{{ number_format($fee_structure['paid_amount'], 2) }}</td>
                    <td class="amount-cell">₹{{ number_format($fee_structure['remaining_amount'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Payment Details -->
        <div class="payment-details">
            <h3 style="margin-bottom: 10px; color: #2c3e50;">Payment Details</h3>
            <div class="info-row">
                <span class="info-label">Amount Paid:</span>
                <span class="info-value"><strong>₹{{ number_format($payment['amount'], 2) }}</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value">{{ $payment['method'] }}</span>
            </div>
            @if(!empty($payment['transaction_id']))
            <div class="info-row">
                <span class="info-label">Transaction ID:</span>
                <span class="info-value">{{ $payment['transaction_id'] }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">
                    <span class="status-badge status-{{ strtolower($payment['status']) }}">
                        {{ $payment['status'] }}
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Received By:</span>
                <span class="info-value">{{ $payment['received_by'] }}</span>
            </div>
        </div>

        <!-- Amount Summary -->
        <div class="amount-section">
            <div class="amount-row">
                <span>Payment Amount:</span>
                <span>₹{{ number_format($payment['amount'], 2) }}</span>
            </div>
            <div class="amount-row total">
                <span>Amount Received:</span>
                <span>₹{{ number_format($payment['amount'], 2) }}</span>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Student/Parent Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Cashier Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Principal Signature</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Note:</strong> This is a computer-generated receipt and does not require a signature.</p>
            <p>Please keep this receipt for your records. For any queries, contact the school office.</p>
            <p>Generated on: {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
