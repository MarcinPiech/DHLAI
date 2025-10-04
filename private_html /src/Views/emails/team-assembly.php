<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan prac montażowych - <?= $week->week_number ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        th {
            background: #667eea;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .location-address {
            font-weight: 600;
            color: #667eea;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #856404;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 13px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Plan prac montażowych</h1>
            <p>Tydzień: <?= $week->week_number ?> / <?= $week->year ?></p>
        </div>

        <div class="greeting">
            Dzień dobry <strong><?= htmlspecialchars($team_member) ?></strong>,
        </div>

        <p>
            Poniżej znajduje się plan prac montażowych na tydzień <strong><?= $week->week_number ?></strong>.
            Proszę o zapoznanie się z harmonogramem i przygotowanie się do realizacji zleceń.
        </p>

        <?php if (!empty($locations)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">Lp.</th>
                    <th style="width: 35%;">Lokalizacja</th>
                    <th style="width: 15%;">DHL HS</th>
                    <th style="width: 20%;">Monterzy</th>
                    <th style="width: 25%;">Uwagi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $index => $location): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td class="location-address">
                        <?= htmlspecialchars($location->getFullAddress()) ?>
                        <?php if ($location->address_coords): ?>
                        <br><small style="color: #666;">📍 <?= htmlspecialchars($location->address_coords) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($location->dhl_hs ?: '-') ?></td>
                    <td>
                        <?php
                        $teams = array_filter([
                            $location->team_assembly1,
                            $location->team_assembly2,
                            $location->team_assembly3
                        ]);
                        echo htmlspecialchars(implode(', ', $teams));
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($location->protocol_path)): ?>
                        <span style="color: #28a745;">✓ Protokół</span><br>
                        <?php endif; ?>
                        <?php if (!empty($location->photos_paths)): ?>
                        <span style="color: #17a2b8;">📷 Zdjęcia (<?= count($location->photos_paths) ?>)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="info-box">
            <strong>ℹ️ Ważne informacje:</strong>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <li>Prosimy o potwierdzenie odbioru tego maila</li>
                <li>W przypadku pytań lub wątpliwości prosimy o kontakt</li>
                <li>Protokoły przedinstalacyjne dostępne w Dropbox</li>
                <li>Zdjęcia lokalizacji znajdziesz w folderze zespołu</li>
            </ul>
        </div>

        <?php else: ?>
        <div class="info-box">
            <strong>⚠️ Brak przypisanych lokalizacji</strong>
            <p style="margin: 10px 0 0 0;">
                Nie znaleziono lokalizacji przypisanych do Twojej ekipy w tym tygodniu.
            </p>
        </div>
        <?php endif; ?>

        <p style="margin-top: 30px;">
            W razie pytań lub problemów prosimy o kontakt:<br>
            📧 Email: <a href="mailto:operacyjne@apm-service.com">operacyjne@apm-service.com</a><br>
            📞 Telefon: [NUMER TELEFONU]
        </p>

        <p>
            Pozdrawiam,<br>
            <strong>Zespół APM Operacyjne</strong>
        </p>

        <div class="footer">
            <p>
                <strong>APM Service Sp. z o.o.</strong><br>
                Automatyczne powiadomienie z systemu APM Automation<br>
                Data wygenerowania: <?= date('Y-m-d H:i:s') ?>
            </p>
            <p style="font-size: 11px; color: #999; margin-top: 10px;">
                Ten email został wygenerowany automatycznie. Nie odpowiadaj na tę wiadomość.
            </p>
        </div>
    </div>
</body>
</html>