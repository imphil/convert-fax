#!/usr/bin/env php
<?php
/*
 * Comfort Pro Fax Converter
 *
 * Copyright 2010-2011  Philipp Wagner <mail@philipp-wagner.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
 
/*
 * Requires sfftobmp command line utility
 */
require_once 'Config.php';
require_once '3rdparty/phpmailer/class.phpmailer.php';
require_once 'clear_comfort_pro_inbox.php';

$config =& Config::getInstance();
$config->setConfigFile(dirname(__FILE__).'/config.ini');

$imapConnection = imap_open($config->getValue('mailaccount/mailbox'),
                            $config->getValue('mailaccount/user'),
                            $config->getValue('mailaccount/password'));
if ($imapConnection === false) {
    echo  'Unable to establish IMAP connection';
    exit(1);
}

$mails = imap_search($imapConnection, 'UNSEEN'); // ALL
if ($mails === false) {
    exit(0);
}

foreach ($mails as $msgId) {
    $msgBody = imap_fetchbody($imapConnection, $msgId, '1');
    $attachment = imap_fetchbody($imapConnection, $msgId, '2');

    // get message filename from structure
    $msgStruct = imap_fetchstructure($imapConnection, $msgId);
    $filename = '';
    $parameters = $msgStruct->parts[1]->dparameters;
    foreach ($parameters as $param) {
        if ($param->attribute == 'filename') {
            $filename = $param->value;
            break;
        }
    }

    // example: 10_16_22_00_008954592422_0003.sff
    $filenameSplit = explode('_', $filename);

    if ($filenameSplit[4][0] == '0') {
        $absender = substr($filenameSplit[4], 1);
    } else {
        $absender = $filenameSplit[4];
    }

    $newMailText =<<<EOL
Hallo,

eine neues Fax ist eingetroffen.

Absender:    $absender
Zeit:        {$filenameSplit[1]}.{$filenameSplit[0]} um {$filenameSplit[2]}:{$filenameSplit[3]} Uhr

Inverssuche: http://www.dastelefonbuch.de/?kw=$absender&cmd=search

Das Fax ist als PDF-Datei angehängt.

Viele Grüße,

die Telefonanlage
EOL;

    // convert attachment SFF to PDF
    $tmpfile = tempnam(sys_get_temp_dir(), 'faxtomail');
    file_put_contents($tmpfile, base64_decode($attachment));
    $output = '';
    $returnVar = 0;
    exec("sfftobmp -tif $tmpfile -o $tmpfile.tif", $output, $returnVar);
    if ($returnVar) {
        echo "sfftobmp failed: $output\n";
        continue;
    }
    unlink($tmpfile);

    exec("tiff2pdf $tmpfile.tif > $tmpfile.pdf", $output, $returnVar);
    //exec("convert $tmpfile.tif $tmpfile.pdf", $output, $returnVar);
    if ($returnVar) {
        echo "convert failed: ".implode('', $output)."\n";
        continue;
    }
    unlink("$tmpfile.tif");

    exec("/opt/ABBYYOCR/abbyyocr -rl GermanNewSpelling German  ".
         "-if $tmpfile.pdf ".
         "-f PDF -pem ImageOnText -pfpr 200 -pfq 80 -of $tmpfile.ocr.pdf",
         $output, $returnVar);
    if ($returnVar) {
        echo "OCR failed: ".implode('', $output)."\n";
        $outputfile = "$tmpfile.pdf";
    } else {
        $outputfile = "$tmpfile.ocr.pdf";
        unlink("$tmpfile.pdf");
    }

    $filenamePdf = str_replace('sff', 'pdf', $filename);
    if (!copy($outputfile, "/data/fax/$filenamePdf")) {
        echo "Unable to copy $outputfile to /data/fax/$filenamePdf\n";
    }


     // send mail
    $mail = new PHPMailer();

    if ($config->getValue('mailer/method') == 'smtp') {
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = $config->getValue('smtp/host');
        $mail->Port = $config->getValue('smtp/port');
        $mail->Username = $config->getValue('smtp/username');
        $mail->Password = $config->getValue('smtp/password');
    }
    $mail->CharSet = 'UTF8';
    $mail->SetFrom($config->getValue('general/senderAddress'));
    $mail->AddAddress($config->getValue('general/mailTo'));
    $mail->Subject = "Neues Fax von $absender empfangen";
    $mail->Body = $newMailText;
    $mail->AddAttachment($outputfile, $filenamePdf, 'base64', 'application/pdf');

    if (!$mail->Send()) {
        throw new Exception('Unable to send mail: '.$mail->ErrorInfo);
    }
    @unlink("$tmpfile.ocr.pdf");

    imap_delete($imapConnection, $msgId);
}

imap_close($imapConnection);


// clear Comfort Pro inbox to make sure the internal storage isn't overflowing
clear_comfort_pro_inbox($config);
