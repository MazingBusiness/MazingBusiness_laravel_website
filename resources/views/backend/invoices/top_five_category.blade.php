<!DOCTYPE html>
<html>
<head>
    <title>Top 5 Purchased Categories</title>
</head>
<body>

<!-- Header -->
<table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="text-align: right; position: relative;">
            <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display: block;" />
        </td>
    </tr>
</table>

<!-- Main Table -->
<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; margin-top: 25px; font-family: Arial, sans-serif; border: 1px solid #ccc;">
    <thead>
        <tr>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Sr. No</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Category Name</th>
        </tr>
    </thead>
    <tbody>
        @if (!empty($topCategoryNames) && count($topCategoryNames) > 0)
            @foreach($topCategoryNames as $key => $category)
                <tr>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ $key + 1 }}</td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ $category }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="2" style="text-align: center; border: 1px solid #ccc;">No Categories Found</td>
            </tr>
        @endif
    </tbody>
</table>

<!-- Footer -->
<table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 20px;">
    <tbody>
        <tr bgcolor="#174e84">
            <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                ACE TOOLS PVT LTD - TOP 5 CATEGORIES
            </td>
        </tr>
    </tbody>
</table>

</body>
</html>
