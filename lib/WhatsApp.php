<?php
class WhatsApp {

    private static function request(array $body): array {
        $url = 'https://graph.facebook.com/v19.0/' . WA_PHONE_NUMBER_ID . '/messages';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . WA_ACCESS_TOKEN,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => json_decode($response, true)];
    }

    public static function sendText(string $to, string $text, ?string $contextId = null): array {
        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $text],
        ];
        if ($contextId) $body['context'] = ['message_id' => $contextId];
        return self::request($body);
    }

    public static function sendImage(string $to, string $mediaId, ?string $caption = null): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'image',
            'image' => array_filter(['id' => $mediaId, 'caption' => $caption]),
        ]);
    }

    public static function sendDocument(string $to, string $mediaId, string $filename): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'document',
            'document' => ['id' => $mediaId, 'filename' => $filename],
        ]);
    }

    public static function sendAudio(string $to, string $mediaId): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'audio',
            'audio' => ['id' => $mediaId],
        ]);
    }

    public static function sendVideo(string $to, string $mediaId, ?string $caption = null): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'video',
            'video' => array_filter(['id' => $mediaId, 'caption' => $caption]),
        ]);
    }

    public static function sendLocation(string $to, float $lat, float $lng, string $name = '', string $address = ''): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'location',
            'location' => ['latitude' => $lat, 'longitude' => $lng, 'name' => $name, 'address' => $address],
        ]);
    }

    public static function sendReaction(string $to, string $targetMessageId, string $emoji): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'reaction',
            'reaction' => ['message_id' => $targetMessageId, 'emoji' => $emoji],
        ]);
    }

    public static function sendButtons(string $to, string $bodyText, array $buttons): array {
        // $buttons = [['id' => 'btn1', 'title' => 'Sim'], ...]  — máx 3
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_map(fn($b) => [
                        'type'  => 'reply',
                        'reply' => ['id' => $b['id'], 'title' => $b['title']],
                    ], $buttons),
                ],
            ],
        ]);
    }

    public static function sendTemplate(string $to, string $templateName, string $language, array $components = []): array {
        return self::request([
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'template',
            'template' => [
                'name'       => $templateName,
                'language'   => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Download de mídia recebida (retorna binário)
     */
    public static function downloadMedia(string $mediaId): ?string {
        // 1. Buscar URL da mídia
        $url = 'https://graph.facebook.com/v19.0/' . $mediaId;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . WA_ACCESS_TOKEN],
        ]);
        $resp    = json_decode(curl_exec($ch), true);
        $mediaUrl = $resp['url'] ?? null;
        curl_close($ch);

        if (!$mediaUrl) return null;

        // 2. Baixar o arquivo
        $ch = curl_init($mediaUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . WA_ACCESS_TOKEN],
        ]);
        $binary = curl_exec($ch);
        curl_close($ch);

        return $binary ?: null;
    }
}
