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
        $form->number('sync_interval', '同期間隔（分）')
            ->default(15)
            ->help('カレンダーデータを更新する頻度を指定します。')
            ->rules('required|integer|min:1');

        $form->html('<hr>');

        for ($i = 1; $i <= 5; $i++) {
            $form->html("<h4>カレンダー {$i}</h4>");
            $form->text("ical_url_{$i}", "iCal URL {$i}")
                ->help("Googleカレンダーの「iCal形式の非公開URL」")
                ->rules('nullable|url');
            $form->text("calendar_name_{$i}", "表示名 {$i}")
                ->default("カレンダー {$i}");
            $form->color("calendar_color_{$i}", "色 {$i}")
                ->default('#4F46E5');
        }
    }

    /**
     * Main page entry point.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $calendars = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = $this->plugin->getCustomOption("ical_url_{$i}");
            if ($url) {
                $calendars[] = [
                    'url' => $url,
                    'name' => $this->plugin->getCustomOption("calendar_name_{$i}", "カレンダー {$i}"),
                    'color' => $this->plugin->getCustomOption("calendar_color_{$i}", '#4F46E5'),
                ];
            }
        }

        $config = [
            'calendars' => $calendars,
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
