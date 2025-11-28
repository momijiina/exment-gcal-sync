<?php

namespace App\Plugins\GoogleCalendarViewer;

use Exceedone\Exment\Services\Plugin\PluginPageBase;

class Plugin extends PluginPageBase
{
    protected $useCustomOption = true;

    /**
     * プラグイン設定画面でカスタムオプションを定義
     *
     * @param $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        $form->number('sync_interval', '同期間隔（分）')
            ->default(15)
            ->help('カレンダーデータを更新する頻度を指定します。')
            ->rules('required|integer|min:1');

        $form->html('<hr>');

        for ($i = 1; $i <= 5; $i++) {
            $form->html("<h4>カレンダー {$i}</h4>");
            $form->text("ical_url_{$i}", "iCal URL {$i}")
                ->help("Googleカレンダーの「iCal形式の非公開URL」を入力してください")
                ->rules('nullable|url');
            $form->text("calendar_name_{$i}", "表示名 {$i}")
                ->default("カレンダー {$i}");
            $form->color("calendar_color_{$i}", "色 {$i}")
                ->default('#4F46E5');
        }
    }

    /**
     * メインページのエントリーポイント
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // カスタムオプションの取得（custom_optionsはJSON文字列の場合がある）
        $customOptions = $this->plugin->custom_options;
        if (is_string($customOptions)) {
            $customOptions = json_decode($customOptions, true) ?? [];
        } elseif (!is_array($customOptions)) {
            $customOptions = [];
        }

        $calendars = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = array_get($customOptions, "ical_url_{$i}");
            if (!empty($url) && trim($url) !== '') {
                $calendars[] = [
                    'url' => $url,
                    'name' => array_get($customOptions, "calendar_name_{$i}") ?: "カレンダー {$i}",
                    'color' => array_get($customOptions, "calendar_color_{$i}") ?: '#4F46E5',
                ];
            }
        }

        $config = [
            'calendars' => $calendars,
            'syncInterval' => (int) (array_get($customOptions, 'sync_interval') ?: 15),
        ];

        $htmlPath = __DIR__ . '/resources/assets/index.html';
        if (file_exists($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            
            // プロキシURLを追加
            $config['proxyUrl'] = $this->plugin->getFullUrl('proxy');
            
            // 設定情報をJSONとして安全に注入
            // まずUTF-8で正しくエンコード
            foreach ($calendars as &$calendar) {
                if (isset($calendar['name'])) {
                    $calendar['name'] = mb_convert_encoding($calendar['name'], 'UTF-8', 'UTF-8');
                }
            }
            $config['calendars'] = $calendars;
            
            // JSON_HEX_* フラグで特殊文字をエスケープ
            $configJson = json_encode(
                $config, 
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | 
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            
            // HTML属性用にエスケープ（htmlspecialcharsが最も安全）
            $configJsonEscaped = htmlspecialchars($configJson, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // divタグのdata属性を置換
            $htmlContent = preg_replace(
                '/<div id="exment-calendar-config"[^>]*data-config=\'[^\']*\'[^>]*><\/div>/',
                '<div id="exment-calendar-config" style="display:none;" data-config=\'' . $configJsonEscaped . '\'></div>',
                $htmlContent
            );
        } else {
            $htmlContent = '<h1>Error: カレンダービューワーのファイルが見つかりません。</h1>';
        }

        return $this->pluginView('index', ['htmlContent' => $htmlContent]);
    }

    /**
     * iCalデータ取得のプロキシエンドポイント（CORS回避用）
     *
     * @return \Illuminate\Http\Response
     */
    public function proxy()
    {
        $request = request();
        $targetUrl = $request->input('url');

        if (!$targetUrl) {
            return response('URLパラメータが指定されていません', 400);
        }

        // カスタムオプションの取得
        $customOptions = $this->plugin->custom_options;
        if (is_string($customOptions)) {
            $customOptions = json_decode($customOptions, true) ?? [];
        } elseif (!is_array($customOptions)) {
            $customOptions = [];
        }

        // セキュリティチェック: 設定されたURLのみを許可
        $allowedUrls = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = array_get($customOptions, "ical_url_{$i}");
            if (!empty($url) && trim($url) !== '') {
                $allowedUrls[] = $url;
            }
        }

        $isAllowed = false;
        foreach ($allowedUrls as $allowed) {
            if ($targetUrl === $allowed) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return response('このURLは許可されていません', 403);
        }

        // iCalデータを取得
        $options = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: ExmentGoogleCalendarViewer/2.0\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents($targetUrl, false, $context);

        if ($content === false) {
            return response('カレンダーデータの取得に失敗しました', 502);
        }

        return response($content, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
