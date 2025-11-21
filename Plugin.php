<?php

namespace App\Plugins\ExmentGoogleCalendar;

use Exceedone\Exment\Services\Plugin\PluginPageBase;

class Plugin extends PluginPageBase
{
    protected $useCustomOption = true;

    /**
     * Define custom options for the plugin settings page.
     *
     * @param $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        $form->text('ical_url', 'iCal URL')
            ->help('Googleカレンダーの設定から「iCal形式の非公開URL」を入力してください。')
            ->rules('required|url');

        $form->number('sync_interval', '同期間隔（分）')
            ->default(15)
            ->help('カレンダーデータを更新する頻度を指定します。')
            ->rules('required|integer|min:1');
    }

    /**
     * Main page entry point.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $config = [
            'icalUrl' => $this->plugin->getCustomOption('ical_url'),
            'syncInterval' => (int) $this->plugin->getCustomOption('sync_interval', 15),
        ];

        $htmlPath = __DIR__ . '/resources/assets/index.html';
        if (file_exists($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            // Inject config before the closing body tag
            $configScript = '<script>window.exmentGoogleCalendarConfig = ' . json_encode($config) . ';</script>';
            $htmlContent = str_replace('</body>', $configScript . '</body>', $htmlContent);
        } else {
            $htmlContent = '<h1>Error: App build not found.</h1>';
        }

        return $this->pluginView('index', ['htmlContent' => $htmlContent]);
    }
}
