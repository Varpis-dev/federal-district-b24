<?php

const LEAD_FED_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';
const DEAL_FED_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';

function jsonResponse($data)
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_PRETTY_PRINT |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function getBaseUrl()
{
    $host = $_SERVER['HTTP_HOST'] ?? '';

    return 'https://' . $host;
}

function normalizeAuth($input)
{
    $auth = [];

    if (
        isset($input['auth']) &&
        is_array($input['auth'])
    ) {
        $auth = $input['auth'];
    }

    if (
        isset($input['AUTH']) &&
        is_array($input['AUTH'])
    ) {
        $auth = array_merge(
            $auth,
            $input['AUTH']
        );
    }

    if (empty($auth['access_token'])) {

        if (!empty($input['AUTH_ID'])) {
            $auth['access_token'] =
                $input['AUTH_ID'];

        } elseif (
            !empty($input['access_token'])
        ) {
            $auth['access_token'] =
                $input['access_token'];

        } elseif (
            !empty($input['AUTH']['ACCESS_TOKEN'])
        ) {
            $auth['access_token'] =
                $input['AUTH']['ACCESS_TOKEN'];
        }
    }

    if (empty($auth['domain'])) {

        if (!empty($input['DOMAIN'])) {
            $auth['domain'] =
                $input['DOMAIN'];

        } elseif (!empty($input['domain'])) {
            $auth['domain'] =
                $input['domain'];

        } elseif (
            !empty($input['AUTH']['DOMAIN'])
        ) {
            $auth['domain'] =
                $input['AUTH']['DOMAIN'];
        }
    }

    return $auth;
}

function callBitrix(
    $method,
    $params,
    $auth
) {
    if (
        empty($auth['domain']) ||
        empty($auth['access_token'])
    ) {
        return [
            'error' => 'NO_AUTH',
            'error_description' =>
                'Нет domain или access_token'
        ];
    }

    $domain = preg_replace(
        '/[^a-zA-Z0-9\.\-]/',
        '',
        $auth['domain']
    );

    $url =
        'https://' .
        $domain .
        '/rest/' .
        $method .
        '.json';

    $params['auth'] =
        $auth['access_token'];

    $context =
        stream_context_create([
            'http' => [
                'method' =>
                    'POST',

                'header' =>
                    "Content-Type: application/x-www-form-urlencoded\r\n",

                'content' =>
                    http_build_query($params),

                'timeout' =>
                    12,

                'ignore_errors' =>
                    true
            ]
        ]);

    $raw =
        @file_get_contents(
            $url,
            false,
            $context
        );

    if ($raw === false) {
        return [
            'error' =>
                'HTTP_REQUEST_FAILED',

            'error_description' =>
                'Не удалось выполнить запрос к Bitrix24'
        ];
    }

    $decoded =
        json_decode(
            $raw,
            true
        );

    if (!is_array($decoded)) {
        return [
            'error' =>
                'BAD_JSON',

            'json_error' =>
                json_last_error_msg(),

            'raw_length' =>
                strlen($raw),

            'raw_start' =>
                substr($raw, 0, 1000)
        ];
    }

    return $decoded;
}
