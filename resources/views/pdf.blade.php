<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <table style="width:100%">
        @php
            $image_str = str_replace('\\', '/', setting('admin.logo_security'));
            $image_str_crr = str_replace('\\', '/', setting('admin.logo_crr'));
        @endphp
        <tr>
            <td style="text-align: left;width:20%"><img
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('storage/' . $image_str))) }}"
                    style="width: 70px" alt=""></td>
            <td style="text-align: center;width:80%">DIVISI SECURITY <br><u>BERITA ACARA
                    KEJADIAN</u><br>No.{{ $id }}/BAKSEC.
                CRR/{{ date('M', strtotime($date)) }}/{{ date('Y', strtotime($date)) }}</td>
            <td style="text-align: right;width:20%"><img
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('storage/' . $image_str_crr))) }}"
                    style="width: 130px" alt=""></td>
        </tr>
    </table>
    <br>
    <p>Pada hari {{ hari_ini($date) }}, {{ date('d', strtotime($date)) }} {{ date('M', strtotime($date)) }}
        {{ date('Y', strtotime($date)) }} <br>Pukul
        {{ date('h:i:sa', strtotime($date)) }} WIB</p>
    <p>Bertempatdi : {{ $bertempatdi }}</p>
    <p style="word-wrap: break-word">Melaporkan bahwa Telah Terjadi :
        <br>{{ str_pad(html_entity_decode(strip_tags($description)), 1500, ' . ') }}
    </p>
    <p>Tindakan yang diambil : <br>{{ str_pad(html_entity_decode(strip_tags($action)), 1500, ' . ') }}</p>
    <p>Demikian surat ini dibuat sebagaimana mestinya</p>
    <table style="width:100%">
        <tr>
            <td style="width: 50%">
                Jakarta,{{ date('d', strtotime($date)) }}/{{ date('m', strtotime($date)) }}/{{ date('Y', strtotime($date)) }}
            </td>
            <td style="width: 50%;text-align:center">
            </td>
        </tr>
        <tr>
            <td valign="top">
                Yang membuat
                <br><br><br><br><br>
                {{ $membuat }}
            </td>
            <td style="width: 50%;text-align:center" valign="top">
                Mengetahui
                <br><br><br><br><br>
                {{ $mengetahui }}
            </td>
        </tr>
    </table>

</body>

</html>


<?php

function hari_ini($tgl)
{
    $hari = date('D', strtotime($tgl));

    switch ($hari) {
        case 'Sun':
            $hari_ini = 'Minggu';
            break;

        case 'Mon':
            $hari_ini = 'Senin';
            break;

        case 'Tue':
            $hari_ini = 'Selasa';
            break;

        case 'Wed':
            $hari_ini = 'Rabu';
            break;

        case 'Thu':
            $hari_ini = 'Kamis';
            break;

        case 'Fri':
            $hari_ini = 'Jumat';
            break;

        case 'Sat':
            $hari_ini = 'Sabtu';
            break;

        default:
            $hari_ini = 'Tidak di ketahui';
            break;
    }

    return $hari_ini;
}
?>
