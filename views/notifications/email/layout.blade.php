<!DOCTYPE html>
<html lang="{{evo()->getConfig('lang', 'uk')}}" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>table, td, div, h1, p{font-family: Arial, sans-serif;}</style>
</head>
<body style="margin:0;padding:0" bgcolor="#ffffff">@yield('content')</body>
{{-- You can check the variables available in the template via @dd(get_defined_vars()['__data']) --}}