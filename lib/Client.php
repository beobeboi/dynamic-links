<?php

namespace DiagVN\DynamicLink;

use GuzzleHttp\Client as GuzzleClient;

class Client
{
    protected string $domain = 'https://firebasedynamiclinks.googleapis.com';
    protected string $version = 'v1';

    public function __construct(protected GuzzleClient $client)
    {

    }

    public function createLink(string $link, ?string $title = null, ?string $description = null, ?string $image = null, string $suffix = 'SHORT')
    {
        if (empty($title)) {
            $title = 'Trang chủ - Diag';
        }
        if (empty($description)) {
            $description = 'Từ năm 1998 đến nay, Diag luôn nỗ lực nâng cao hiệu quả khám chữa bệnh tại Việt Nam qua dịch vụ xét nghiệm tối ưu hóa, đáng tin cậy.';
        }
        if (empty($image)) {
            $image = 'https://diag.vn/wp-content/uploads/2023/05/img2.3ce88933.png';
        }
        $option['json'] = [
            'dynamicLinkInfo' => [
                'domainUriPrefix' => config('dynamic_link.domain'),
                'link' => $link,
                'socialMetaTagInfo' => [
                    'socialTitle' => $title,
                    'socialDescription' => $description,
                    'socialImageLink' => $image,
                ],
            ],
            'suffix' => [
                'option' => $suffix,
            ],
        ];
        $option['query'] = [
            'key' => config('dynamic_link.api_key'),
        ];
        $running = true;
        $tried = 1;
        $response = null;
        while ($running) {
            try {
                $response = $this->client->request(
                    'POST',
                    $this->domain . '/' . $this->version . '/shortLinks',
                    $option
                );
                $running = false;
            } catch (\Exception $exception) {
                report($exception);
                if ($tried > 3) {
                    $running = false;
                } else {
                    $tried++;
                    sleep(1);
                }
            }
        }

        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            return [];
        }
    }
}
