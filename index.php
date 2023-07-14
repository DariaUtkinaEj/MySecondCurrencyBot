<?php

require_once __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;

$token = '6343027342:AAFHxd2hwHgDGB_XTZS7IihruukSczebpJI';

$telegram = new Api($token);
$update = $telegram->getWebhookUpdates();

file_put_contents(__DIR__ . '/logs.txt', print_r($update, 1), FILE_APPEND);

$chat_id = $update['message']['chat']['id'] ?? '';
$text = $update['message']['text'] ?? '';

refreshDb();

if ($text == '/start') {
    $response = $telegram->sendMessage([
        'chat_id' => $chat_id,
        'text' => 'Привет, ' . $update['message']['chat']['first_name'] . '! Я бот, который знает стоимость всех популярных валют. Указывать в международном стандарте: - ISO4217 standart' . "\n" . 'Примеры: <b>USD</b>, <b>EUR</b>, <b>RUB</b>, <b>UAH</b>',
        'parse_mode' => 'HTML',
    ]);
} elseif (!empty($text)) {
    $text = strtoupper($text);
    $text = trim($text);

    if (!preg_match('/[A-Z]{3,5}/', $text)) {
        $response = $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Введите корректный формат валюты',
            'parse_mode' => 'HTML',
        ]);
    } else {
        $jsonCurrencyLegend = file_get_contents(__DIR__ . '/currencyDb.json');
        $decodedJsonLegend = json_decode($jsonCurrencyLegend, true);

        $jsonCurrency = file_get_contents(__DIR__ . '/currencyValues.json');
        $decodedJson = json_decode($jsonCurrency, true);

        if (!array_key_exists($text, $decodedJson['data']) || !array_key_exists($text, $decodedJsonLegend)) {
            $response = $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Валюта с кодом $text не найдена",
                'parse_mode' => 'HTML',
            ]);
        } else {
            $searchedCurrencyLegend = $decodedJsonLegend[$text];
            $currencyName = $searchedCurrencyLegend['name'];
            $currencySymbol = $searchedCurrencyLegend['symbol_native'];

            $searchedCurrency = $decodedJson['data'][$text];
            $currencyValue = $searchedCurrency['value'];

            $response = $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Курс валюты <b>$text</b> <i>($currencyName, $currencySymbol)</i>: <b>$currencyValue</b>",
                'parse_mode' => 'HTML',
            ]);
        }
    }
}

function refreshDb()
{
    $lastUpdate = file_get_contents(__DIR__ . '/lastUpdate');

    $dateTimeImmutable = DateTimeImmutable::createFromFormat('d.m.Y', $lastUpdate);
    $dateTimeImmutableNow = new DateTimeImmutable();

    $dateTimeImmutable = $dateTimeImmutable->modify('next day');
    if ($dateTimeImmutable->format('d.m.Y') === $dateTimeImmutableNow->format('d.m.Y')) {
        return;
    }

    $currencyToken = 'cur_live_Nf5h5Qc1gP4F0jawf1ezU84SP4SstubAgJvZX3Yn';
    $request = "https://api.currencyapi.com/v3/latest?apikey=$currencyToken&currencies=";
    $jsonCurrency = file_get_contents(__DIR__ . '/currencyDb.json');
    $decodedJson = json_decode($jsonCurrency);

    foreach ($decodedJson as $key => $value) {
        $request .= $key . '%2C';
    }

    $request = rtrim($request, '%2C');

    $ch = curl_init($request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . '/currencyValues.json', $result);
    file_put_contents(__DIR__ . '/lastUpdate', $dateTimeImmutableNow->format('d.m.Y'));
}