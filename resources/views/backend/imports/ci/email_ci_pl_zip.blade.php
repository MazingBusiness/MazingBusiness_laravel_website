<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CI + PL ZIP</title>
</head>
<body>
    <p>Dear {{ $name }},</p>

    <p>
        Please find attached the combined
        <strong>Commercial Invoice &amp; Packing List ZIP</strong>
        for the following BL:
    </p>

    <ul>
        <li><strong>BL No:</strong> {{ $bl->bl_no ?? $bl->id }}</li>
        <li><strong>Import Company:</strong> {{ optional($bl->importCompany)->company_name ?? '-' }}</li>
    </ul>

    <p>Regards,<br>
    Mazing Business Import Module</p>
</body>
</html>
